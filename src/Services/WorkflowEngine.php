<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\DatabaseManager;
use Illuminate\Support\Collection;
use Qnox\Workflows\Contracts\{RoleAssigneeResolver, SupervisorResolver, UserProvider};
use Qnox\Workflows\Events\{ApprovalLevelEntered, ApprovalRecorded, WorkflowApproved, WorkflowRejected, WorkflowStarted};
use Qnox\Workflows\Exceptions\{WorkflowConflictException, WorkflowException};
use Qnox\Workflows\Models\{Workflow, WorkflowAction, WorkflowInboxItem, WorkflowInstance, WorkflowInstanceApprover, WorkflowInstanceLevel, WorkflowLevel};

class WorkflowEngine
{
    public function __construct(
        protected DatabaseManager $db,
        protected SupervisorResolver $supervisors,
        protected RoleAssigneeResolver $roles,
        protected UserProvider $users,
    ) {}

    public function start(object $subject, Workflow $workflow, Authenticatable $initiator, array $context = []): WorkflowInstance
    {
        if (!$workflow->is_active) throw new WorkflowException('The selected workflow is inactive.');
        $first = $workflow->levels()->with('selectedUsers')->orderBy('sequence')->first();
        if (!$first) throw new WorkflowException('The selected workflow has no approval levels.');

        return $this->db->transaction(function () use ($subject, $workflow, $initiator, $context, $first) {
            $approvers = $this->resolveApprovers($first, $initiator, $context);
            $now = now();
            $instance = WorkflowInstance::create([
                'workflow_id' => $workflow->id,
                'subject_type' => $this->type($subject), 'subject_id' => $subject->getKey(),
                'initiator_type' => $this->type($initiator), 'initiator_id' => $initiator->getAuthIdentifier(),
                'current_level_id' => $first->id, 'status' => 'in_progress', 'context' => $context, 'submitted_at' => $now,
            ]);
            $snapshot = $this->enterLevel($instance, $first, $approvers, $now);
            $result = $instance->fresh($this->relations());
            $this->afterCommit(fn () => event(new WorkflowStarted($result, $initiator)));
            $this->afterCommit(fn () => event(new ApprovalLevelEntered($result, $initiator, null, ['instance_level_id' => $snapshot->id])));
            return $result;
        });
    }

    public function approve(WorkflowInstance $instance, Authenticatable $actor, ?string $comment = null): WorkflowInstance
    { return $this->decide($instance, $actor, 'approve', $comment); }

    public function reject(WorkflowInstance $instance, Authenticatable $actor, ?string $comment = null): WorkflowInstance
    { return $this->decide($instance, $actor, 'reject', $comment); }

    public function canApprove(WorkflowInstance $instance, Authenticatable $actor): bool { return $this->canDecide($instance, $actor); }
    public function canReject(WorkflowInstance $instance, Authenticatable $actor): bool { return $this->canDecide($instance, $actor); }
    public function currentApprovalLevel(WorkflowInstance $instance): ?WorkflowInstanceLevel { return $instance->currentApprovalLevel(); }
    public function resolvedApprovers(WorkflowInstance $instance): Collection { return $instance->currentApprovalLevel()?->approvers()->get() ?? collect(); }
    public function approvalHistory(WorkflowInstance $instance): Collection { return $instance->actions()->oldest()->get(); }
    public function availableActions(WorkflowInstance $instance, Authenticatable $actor): array
    { return $this->canDecide($instance, $actor) ? [['action' => 'approve', 'label' => 'Approve'], ['action' => 'reject', 'label' => 'Reject']] : []; }

    protected function decide(WorkflowInstance $instance, Authenticatable $actor, string $decision, ?string $comment): WorkflowInstance
    {
        return $this->db->transaction(function () use ($instance, $actor, $decision, $comment) {
            $instance = WorkflowInstance::query()->with('workflow')->lockForUpdate()->findOrFail($instance->id);
            if (in_array($instance->status, ['approved', 'rejected', 'cancelled'], true)) throw new WorkflowConflictException('This workflow has already reached a final outcome.');
            $snapshot = WorkflowInstanceLevel::query()->with('approvers')->where('workflow_instance_id', $instance->id)
                ->where('status', 'pending')->lockForUpdate()->latest('id')->first();
            if (!$snapshot) throw new WorkflowConflictException('There is no active approval level.');
            $eligible = $snapshot->approvers->first(fn ($item) => $item->status === 'pending' && $this->same($item, $actor));
            if (!$eligible || !$this->users->isEligible($actor) || $this->isInitiator($instance, $actor)) throw new WorkflowException('You are not eligible to decide this approval level.');
            if ($decision === 'reject' && $snapshot->rejection_comment_required && trim((string) $comment) === '') throw new WorkflowException('A rejection comment is required for this approval level.');

            $now = now();
            $action = WorkflowAction::create([
                'workflow_instance_id' => $instance->id, 'workflow_instance_level_id' => $snapshot->id,
                'actor_type' => $this->type($actor), 'actor_id' => $actor->getAuthIdentifier(), 'action' => $decision, 'comment' => $comment,
            ]);
            $snapshot->update(['status' => $decision === 'approve' ? 'approved' : 'rejected', 'actioned_at' => $now, 'exited_at' => $now]);
            $snapshot->approvers()->update(['status' => 'closed', 'acted_at' => $now]);
            $eligible->update(['status' => $decision === 'approve' ? 'approved' : 'rejected', 'acted_at' => $now]);
            $this->closeInbox($snapshot, $action, $actor, $now);

            if ($decision === 'reject') {
                $instance->update(['status' => 'rejected', 'rejected_at' => $now, 'last_action_at' => $now]);
                return $this->dispatchFinal($instance, $actor, 'reject', $comment);
            }

            $next = $instance->workflow->levels()->with('selectedUsers')->where('sequence', '>', $snapshot->level_sequence)->orderBy('sequence')->first();
            if (!$next) {
                $instance->update(['status' => 'approved', 'current_level_id' => null, 'approved_at' => $now, 'last_action_at' => $now]);
                return $this->dispatchFinal($instance, $actor, 'approve', $comment);
            }
            $approvers = $this->resolveApprovers($next, $instance->initiator()->firstOrFail(), $instance->context ?? []);
            $instance->update(['current_level_id' => $next->id, 'last_action_at' => $now]);
            $nextSnapshot = $this->enterLevel($instance, $next, $approvers, $now);
            $result = $instance->fresh($this->relations());
            $this->afterCommit(fn () => event(new ApprovalRecorded($result, $actor, null, ['action' => 'approve'])));
            $this->afterCommit(fn () => event(new ApprovalLevelEntered($result, $actor, null, ['instance_level_id' => $nextSnapshot->id])));
            return $result;
        });
    }

    protected function dispatchFinal(WorkflowInstance $instance, Authenticatable $actor, string $decision, ?string $comment): WorkflowInstance
    {
        $result = $instance->fresh($this->relations());
        $this->afterCommit(fn () => event(new ApprovalRecorded($result, $actor, null, ['action' => $decision])));
        $this->afterCommit(fn () => event($decision === 'approve' ? new WorkflowApproved($result, $actor) : new WorkflowRejected($result, $actor, null, ['comment' => $comment])));
        return $result;
    }

    public function cancel(WorkflowInstance $instance, Authenticatable $actor, ?string $comment = null): WorkflowInstance
    {
        return $this->db->transaction(function () use ($instance, $actor, $comment) {
            $instance = WorkflowInstance::query()->lockForUpdate()->findOrFail($instance->id);
            if (in_array($instance->status, ['approved', 'rejected', 'cancelled'], true)) throw new WorkflowConflictException('This workflow has already reached a final outcome.');
            if (!$this->isInitiator($instance, $actor)) throw new WorkflowException('Only the initiator may cancel this workflow.');
            $now = now();
            $snapshot = $instance->history()->where('status', 'pending')->lockForUpdate()->latest('id')->first();
            if (!$snapshot) throw new WorkflowConflictException('There is no active approval level.');
            $action = WorkflowAction::create([
                'workflow_instance_id' => $instance->id, 'workflow_instance_level_id' => $snapshot->id,
                'actor_type' => $this->type($actor), 'actor_id' => $actor->getAuthIdentifier(), 'action' => 'cancel', 'comment' => $comment,
            ]);
            $snapshot->update(['status' => 'cancelled', 'actioned_at' => $now, 'exited_at' => $now]);
            $snapshot->approvers()->update(['status' => 'closed', 'acted_at' => $now]);
            $snapshot->inboxItems()->update(['status' => 'ended', 'ended_at' => $now, 'workflow_action_id' => $action->id, 'updated_at' => $now]);
            $instance->update(['status' => 'cancelled', 'cancelled_at' => $now, 'last_action_at' => $now]);
            $result = $instance->fresh($this->relations());
            $this->afterCommit(fn () => event(new \Qnox\Workflows\Events\WorkflowCancelled($result, $actor, null, ['comment' => $comment])));
            return $result;
        });
    }

    protected function resolveApprovers(WorkflowLevel $level, Authenticatable $initiator, array $context): Collection
    {
        $resolved = match ($level->approver_type) {
            'supervisor' => collect([$this->supervisors->resolve($initiator, $context)])->filter(),
            'role' => $this->roles->resolve($level->approver_role, $context),
            'users' => $this->users->findMany($level->selectedUsers->pluck('user_id')->all()),
            default => throw new WorkflowException("Unsupported approver type [{$level->approver_type}]."),
        };
        $resolved = $resolved->filter(fn ($user) => $user instanceof Authenticatable && $this->users->isEligible($user))
            ->reject(fn ($user) => $this->type($user) === $this->type($initiator) && (string) $user->getAuthIdentifier() === (string) $initiator->getAuthIdentifier())
            ->unique(fn ($user) => $this->type($user).':'.$user->getAuthIdentifier())->values();
        if ($resolved->isEmpty()) throw new WorkflowException("No eligible approvers could be resolved for level [{$level->name}].");
        return $resolved;
    }

    protected function enterLevel(WorkflowInstance $instance, WorkflowLevel $level, Collection $approvers, mixed $now): WorkflowInstanceLevel
    {
        $snapshot = $instance->history()->create([
            'workflow_level_id' => $level->id, 'level_name' => $level->name, 'level_sequence' => $level->sequence,
            'approver_type' => $level->approver_type, 'rejection_comment_required' => $level->rejection_comment_required,
            'status' => 'pending', 'entered_at' => $now,
        ]);
        foreach ($approvers as $user) {
            $recipient = ['approver_type' => $this->type($user), 'approver_id' => $user->getAuthIdentifier()];
            $snapshot->approvers()->create($recipient + ['status' => 'pending']);
            WorkflowInboxItem::create(['workflow_instance_id' => $instance->id, 'workflow_instance_level_id' => $snapshot->id,
                'recipient_type' => $recipient['approver_type'], 'recipient_id' => $recipient['approver_id'], 'status' => 'pending']);
        }
        return $snapshot;
    }

    protected function closeInbox(WorkflowInstanceLevel $level, WorkflowAction $action, Authenticatable $actor, mixed $now): void
    {
        $level->inboxItems()->update(['status' => 'ended', 'ended_at' => $now, 'workflow_action_id' => $action->id, 'updated_at' => $now]);
        $level->inboxItems()->where('recipient_type', $this->type($actor))->where('recipient_id', $actor->getAuthIdentifier())->update(['status' => 'responded', 'responded_at' => $now]);
    }

    protected function canDecide(WorkflowInstance $instance, Authenticatable $actor): bool
    {
        if ($instance->status !== 'in_progress' || !$this->users->isEligible($actor) || $this->isInitiator($instance, $actor)) return false;
        return $instance->history()->where('status', 'pending')->whereHas('approvers', fn ($q) => $q
            ->where('approver_type', $this->type($actor))->where('approver_id', $actor->getAuthIdentifier())->where('status', 'pending'))->exists();
    }
    protected function same(WorkflowInstanceApprover $item, Authenticatable $actor): bool { return $item->approver_type === $this->type($actor) && (string) $item->approver_id === (string) $actor->getAuthIdentifier(); }
    protected function isInitiator(WorkflowInstance $instance, Authenticatable $actor): bool { return $instance->initiator_type === $this->type($actor) && (string) $instance->initiator_id === (string) $actor->getAuthIdentifier(); }
    protected function type(object $model): string { return method_exists($model, 'getMorphClass') ? $model->getMorphClass() : get_class($model); }
    protected function afterCommit(callable $callback): void { $this->db->connection()->afterCommit($callback); }
    protected function relations(): array { return ['workflow', 'currentLevel', 'history.approvers', 'actions']; }
}
