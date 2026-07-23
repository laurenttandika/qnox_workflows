<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Database\DatabaseManager as DB;
use Qnox\Workflows\Models\Workflow;
use Qnox\Workflows\Models\WorkflowAction;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Models\WorkflowInstanceLevel;
use Qnox\Workflows\Models\WorkflowLevel;
use Qnox\Workflows\Models\WorkflowTransition;
use Qnox\Workflows\Services\Guards\GuardEvaluator;
use Qnox\Workflows\Support\WorkflowStatuses;
use Qnox\Workflows\Events\{
    WorkflowActioned,
    WorkflowActioning,
    WorkflowCompleted,
    WorkflowHeld,
    WorkflowLevelEntered,
    WorkflowLevelExited,
    WorkflowRecalled,
    WorkflowRejected,
    WorkflowResumed,
    WorkflowReturned,
    WorkflowStarted,
    WorkflowStarting
};

class WorkflowEngine
{
    public function __construct(
        protected DB $db,
        protected GuardEvaluator $guards,
        protected WorkflowInbox $inbox,
        protected WorkflowParticipation $participation,
    ) {}

    public function start($subject, Workflow $workflow, User $initiator, array $context = []): WorkflowInstance
    {
        $start = $workflow->startLevel() ?? $workflow->levels()->orderBy('sequence')->firstOrFail();
        $context = $this->normalizeContext($context, $initiator);

        return $this->db->transaction(function () use ($subject, $workflow, $start, $initiator, $context) {
            $pending = new WorkflowInstance([
                'workflow_id' => $workflow->id,
                'subject_type' => $this->morphType($subject),
                'subject_id' => $subject->getKey(),
                'initiator_type' => $this->morphType($initiator),
                'initiator_id' => $initiator->getAuthIdentifier(),
                'current_level_id' => $start->id,
                'status' => WorkflowStatuses::PENDING,
                'context' => $context,
            ]);
            event(new WorkflowStarting($pending, $initiator, null, $context));

            $instance = WorkflowInstance::create([
                'workflow_id' => $workflow->id,
                'subject_type' => $this->morphType($subject),
                'subject_id' => $subject->getKey(),
                'initiator_type' => $this->morphType($initiator),
                'initiator_id' => $initiator->getAuthIdentifier(),
                'current_level_id' => $start->id,
                'status' => WorkflowStatuses::PENDING,
                'context' => $context,
            ]);

            $history = $this->createHistoryEntry($instance, $start, WorkflowStatuses::PENDING);
            $this->inbox->createForLevel($instance->loadMissing('workflow'), $history, $start);
            $this->notifyNextApprovers($instance, $start, $history);

            $result = $instance->fresh(['currentLevel', 'workflow.module', 'history.level']);
            $this->afterCommit(fn () => event(new WorkflowStarted($result, $initiator, null, $context)));
            $this->afterCommit(fn () => event(new WorkflowLevelEntered($result, $initiator)));

            return $result;
        });
    }

    public function submit(WorkflowInstance $instance, User $actor, array $payload = []): WorkflowInstance
    {
        return $this->act($instance, 'submit', $actor, $payload);
    }

    public function approve(WorkflowInstance $instance, User $actor, array $payload = []): WorkflowInstance
    {
        return $this->act($instance, 'approve', $actor, $payload);
    }

    public function reject(WorkflowInstance $instance, User $actor, array $payload = []): WorkflowInstance
    {
        return $this->act($instance, 'reject', $actor, $payload);
    }

    public function hold(WorkflowInstance $instance, User $actor, array $payload = []): WorkflowInstance
    {
        return $this->act($instance, 'hold', $actor, $payload);
    }

    public function return(WorkflowInstance $instance, User $actor, array $payload = []): WorkflowInstance
    {
        return $this->act($instance, 'return', $actor, $payload);
    }

    /** @return array<int,array{action_key:string,label:string,to_level_id:int|null,direction:string,status:string,form_schema:array|null}> */
    public function availableActions(WorkflowInstance $instance, User $actor): array
    {
        $level = $instance->currentLevel()->with(['outgoingTransitions', 'assignments'])->firstOrFail();

        if (!$this->actorCanAct($instance, $level, $actor)) {
            return [];
        }

        return $level->outgoingTransitions
            ->filter(fn (WorkflowTransition $transition) => $this->guards->passes($transition->guard, $instance, $actor))
            ->map(fn (WorkflowTransition $transition) => [
                'action_key' => $transition->action_key,
                'label' => $transition->label ?: $this->labelFor($transition->action_key),
                'to_level_id' => $transition->to_level_id ? (int) $transition->to_level_id : null,
                'direction' => $transition->direction,
                'status' => $this->transitionStatus($transition),
                'form_schema' => $transition->form_schema,
            ])
            ->values()
            ->all();
    }

    public function act(WorkflowInstance $instance, string $actionKey, User $actor, array $payload = []): WorkflowInstance
    {
        return $this->db->transaction(function () use ($instance, $actionKey, $actor, $payload) {
            /** @var WorkflowInstance $instance */
            $instance = WorkflowInstance::query()->lockForUpdate()->findOrFail($instance->id);

            /** @var WorkflowLevel $current */
            $current = WorkflowLevel::query()->with(['assignments', 'outgoingTransitions'])->lockForUpdate()->findOrFail($instance->current_level_id);

            if (!$this->actorCanAct($instance, $current, $actor)) {
                abort(403, 'Not eligible for this level.');
            }

            $transition = $current->outgoingTransitions
                ->firstWhere('action_key', $actionKey);

            if ($transition && !$this->guards->passes($transition->guard, $instance, $actor, $payload)) {
                abort(422, 'Transition guard failed.');
            }

            if (!$transition) {
                abort(422, 'Action is not available for the current workflow level.');
            }

            event(new WorkflowActioning($instance, $actor, $transition, $payload));

            $resolution = $this->resolveTransitionAction($instance, $current, $transition, $actionKey);
            $leavesLevel = $resolution['to_level'] !== null
                || $resolution['instance_status'] === WorkflowStatuses::COMPLETED;

            $currentHistory = $this->currentHistory($instance, $current);
            if ($currentHistory && $leavesLevel) {
                $currentHistory->update([
                    'exited_at' => now(),
                    'forward_date' => now(),
                ]);
            }
            if ($leavesLevel) {
                $this->afterCommit(fn () => event(new WorkflowLevelExited(
                    $instance->fresh(['currentLevel', 'workflow.module']),
                    $actor,
                    $transition,
                    $payload
                )));
            }

            $action = WorkflowAction::create([
                'workflow_instance_id' => $instance->id,
                'from_level_id' => $current->id,
                'to_level_id' => $resolution['to_level']?->id,
                'actor_type' => $this->morphType($actor),
                'actor_id' => $actor->getAuthIdentifier(),
                'action_key' => $actionKey,
                'status' => $resolution['instance_status'],
                'comment' => $payload['comment'] ?? $payload['comments'] ?? null,
                'payload' => $payload,
            ]);

            if ($currentHistory && $leavesLevel) {
                $this->inbox->closeLevel($currentHistory, $actor, $action);
            } elseif ($currentHistory) {
                $this->inbox->recordResponse($currentHistory, $actor, $action);
            }

            $instanceUpdates = [
                'status' => $resolution['instance_status'],
                'current_level_id' => $resolution['to_level']?->id ?? $current->id,
                'last_action_at' => now(),
            ];

            if (($transition->meta['mark_submitted'] ?? false) || ($actionKey === 'submit' && !$instance->submitted_at)) {
                $instanceUpdates['submitted_at'] = now();
            }

            if ($resolution['instance_status'] === WorkflowStatuses::COMPLETED) {
                $instanceUpdates['completed_at'] = now();
            }

            $instance->update($instanceUpdates);

            if ($resolution['to_level']) {
                $nextHistory = $this->createHistoryEntry(
                    $instance->fresh(),
                    $resolution['to_level'],
                    $resolution['history_status'],
                    $currentHistory,
                    $actionKey,
                    $payload['comment'] ?? $payload['comments'] ?? null,
                    $payload['next_user_id'] ?? null
                );
                $this->inbox->createForLevel(
                    $instance->fresh()->loadMissing('workflow'),
                    $nextHistory,
                    $resolution['to_level']
                );

                if (!in_array($resolution['instance_status'], [WorkflowStatuses::COMPLETED, WorkflowStatuses::ON_HOLD], true)) {
                    $this->notifyNextApprovers(
                        $instance->fresh(['currentLevel', 'workflow']),
                        $resolution['to_level'],
                        $nextHistory
                    );
                }
            } elseif ($currentHistory) {
                $currentHistory->update([
                    'status' => $resolution['history_status'],
                    'action_key' => $actionKey,
                    'comments' => $payload['comment'] ?? $payload['comments'] ?? null,
                ]);
            }

            if ($resolution['instance_status'] === WorkflowStatuses::COMPLETED) {
                $this->inbox->closeInstance($instance);
            }

            $result = $instance->fresh(['currentLevel', 'workflow.module', 'history.level', 'actions']);
            $this->afterCommit(fn () => event(new WorkflowActioned($result, $actor, $transition, $payload)));

            if ($resolution['to_level']) {
                $this->afterCommit(fn () => event(new WorkflowLevelEntered($result, $actor, $transition, $payload)));
            }

            if ($eventClass = $this->eventForAction($actionKey, $resolution['instance_status'])) {
                $this->afterCommit(fn () => event(new $eventClass($result, $actor, $transition, $payload)));
            }

            return $result;
        });
    }

    public function notifyNextApprovers(
        WorkflowInstance $instance,
        WorkflowLevel $level,
        ?WorkflowInstanceLevel $history = null
    ): void
    {
        $assignees = $this->participation->eligibleUsers($level, $instance->context ?? []);

        if ($level->assignment_mode !== 'pooled' && $history?->assigned_to_id) {
            $assignees = $assignees->filter(fn ($user) =>
                $this->morphType($user) === $history->assigned_to_type
                && (string) $user->getAuthIdentifier() === (string) $history->assigned_to_id
            );
        }

        foreach ($assignees as $notifiable) {
            $notifiable->notify(new \Qnox\Workflows\Notifications\NextApproverNotification($instance->fresh(['currentLevel'])));
        }
    }

    protected function normalizeContext(array $context, User $initiator): array
    {
        return array_replace_recursive($context, [
            'initiator' => [
                'id' => $initiator->getAuthIdentifier(),
                'type' => $this->morphType($initiator),
            ],
        ]);
    }

    protected function resolveTransitionAction(
        WorkflowInstance $instance,
        WorkflowLevel $current,
        WorkflowTransition $transition,
        string $actionKey
    ): array {
        $to = $transition->to_level_id ? $transition->toLevel()->firstOrFail() : null;
        $status = $this->transitionStatus($transition);
        $completes = $status === WorkflowStatuses::COMPLETED
            || ($to?->is_terminal ?? false)
            || (($transition->meta['complete'] ?? false) === true);

        return [
            'to_level' => $to,
            'instance_status' => $completes ? WorkflowStatuses::COMPLETED : $status,
            'history_status' => $completes ? WorkflowStatuses::COMPLETED : $status,
        ];
    }

    protected function createHistoryEntry(
        WorkflowInstance $instance,
        WorkflowLevel $level,
        string $status,
        ?WorkflowInstanceLevel $parent = null,
        ?string $actionKey = null,
        ?string $comments = null,
        int|string|null $preferredUserId = null
    ): WorkflowInstanceLevel {
        $eligible = $this->participation->eligibleUsers($level, $instance->context ?? []);
        if ($eligible->isEmpty()) {
            abort(422, "No eligible participants are configured for workflow level [{$level->name}].");
        }
        $preferredUserId ??= data_get($instance->context, 'next_user_id');
        $assignee = match ($level->assignment_mode) {
            'pooled' => null,
            'direct' => $eligible->first(fn ($user) =>
                (string) $user->getAuthIdentifier() === (string) $preferredUserId
            ),
            default => $eligible->first(),
        };

        if ($level->assignment_mode === 'direct' && !$assignee) {
            abort(422, 'A permitted next user is required for the direct-assignment level.');
        }

        return $instance->history()->create([
            'workflow_level_id' => $level->id,
            'parent_id' => $parent?->id,
            'assigned_to_type' => $assignee ? $this->morphType($assignee) : null,
            'assigned_to_id' => $assignee?->getAuthIdentifier(),
            'status' => $status,
            'action_key' => $actionKey,
            'comments' => $comments,
            'entered_at' => now(),
            'receive_date' => now(),
            'meta' => [
                'level_name' => $level->name,
                'status_description' => $level->status_description,
            ],
        ]);
    }

    protected function currentHistory(WorkflowInstance $instance, WorkflowLevel $current): ?WorkflowInstanceLevel
    {
        return $instance->history()
            ->where('workflow_level_id', $current->id)
            ->whereNull('exited_at')
            ->latest('id')
            ->first();
    }

    protected function labelFor(string $actionKey): string
    {
        return config('workflows.action_labels.' . $actionKey)
            ?: ucfirst(str_replace('_', ' ', $actionKey));
    }

    protected function transitionStatus(WorkflowTransition $transition): string
    {
        return $transition->status
            ?: data_get($transition->meta, 'status')
            ?: WorkflowStatuses::IN_PROGRESS;
    }

    protected function afterCommit(callable $callback): void
    {
        $this->db->connection()->afterCommit($callback);
    }

    protected function eventForAction(string $actionKey, string $status): ?string
    {
        if ($status === WorkflowStatuses::COMPLETED) {
            return WorkflowCompleted::class;
        }

        return match ($status) {
            WorkflowStatuses::REJECTED => WorkflowRejected::class,
            WorkflowStatuses::RETURNED => WorkflowReturned::class,
            WorkflowStatuses::ON_HOLD => WorkflowHeld::class,
            WorkflowStatuses::RECALLED => WorkflowRecalled::class,
            default => match ($actionKey) {
            'reject' => WorkflowRejected::class,
            'return' => WorkflowReturned::class,
            'hold' => WorkflowHeld::class,
            'resume' => WorkflowResumed::class,
            'recall' => WorkflowRecalled::class,
            default => null,
            },
        };
    }

    protected function actorCanAct(
        WorkflowInstance $instance,
        WorkflowLevel $level,
        User $actor
    ): bool {
        $eligible = $this->participation->eligibleUsers($level, $instance->context ?? []);
        $isEligible = $eligible->contains(fn ($user) =>
            $this->morphType($user) === $this->morphType($actor)
            && (string) $user->getAuthIdentifier() === (string) $actor->getAuthIdentifier()
        );

        if (!$isEligible || !$this->participation->can($actor, $level, 'can_act', $instance->context ?? [])) {
            return false;
        }

        $track = $this->currentHistory($instance, $level);
        if (!$track?->assigned_to_id) {
            return false;
        }

        return $track->assigned_to_type === $this->morphType($actor)
            && (string) $track->assigned_to_id === (string) $actor->getAuthIdentifier();
    }

    protected function morphType(object $model): string
    {
        return method_exists($model, 'getMorphClass') ? $model->getMorphClass() : get_class($model);
    }
}
