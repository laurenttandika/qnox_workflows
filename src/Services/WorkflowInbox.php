<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Support\Collection;
use Qnox\Workflows\Models\WorkflowAction;
use Qnox\Workflows\Models\WorkflowInboxItem;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Models\WorkflowInstanceLevel;
use Qnox\Workflows\Models\WorkflowLevel;
use Qnox\Workflows\Support\WorkflowStatuses;
use Qnox\Workflows\Events\WorkflowClaimed;

class WorkflowInbox
{
    public function __construct(
        protected WorkflowParticipation $participation,
        protected DB $db,
    ) {}

    public function createForLevel(
        WorkflowInstance $instance,
        WorkflowInstanceLevel $instanceLevel,
        WorkflowLevel $level
    ): Collection {
        $users = $this->participation->eligibleUsers($level, $instance->context ?? [], 'can_view');

        if ($level->assignment_mode !== 'pooled' && $instanceLevel->assigned_to_id) {
            $users = $users->filter(fn ($user) =>
                $this->type($user) === $instanceLevel->assigned_to_type
                && (string) $user->getAuthIdentifier() === (string) $instanceLevel->assigned_to_id
            );
        }

        return $users
            ->map(function ($recipient) use ($instance, $instanceLevel, $level) {
                return WorkflowInboxItem::firstOrCreate([
                    'workflow_instance_level_id' => $instanceLevel->id,
                    'recipient_type' => $this->type($recipient),
                    'recipient_id' => $recipient->getAuthIdentifier(),
                ], [
                    'workflow_instance_id' => $instance->id,
                    'status' => WorkflowInboxItem::NEW,
                    'meta' => [
                        'workflow' => $instance->workflow?->name,
                        'level' => $level->name,
                    ],
                ]);
            });
    }

    public function claim(WorkflowInstance $instance, Authenticatable $user): WorkflowInstanceLevel
    {
        return $this->db->transaction(function () use ($instance, $user) {
            $track = WorkflowInstanceLevel::query()
                ->with('level.assignments')
                ->where('workflow_instance_id', $instance->id)
                ->whereNull('exited_at')
                ->lockForUpdate()
                ->latest('id')
                ->firstOrFail();

            abort_unless($track->level->assignment_mode === 'pooled', 422, 'This level is not a pooled assignment.');

            if ($track->assigned_to_id !== null) {
                abort(409, 'This workflow item has already been attended by another user.');
            }

            $eligible = $this->participation->eligibleUsers(
                $track->level,
                $instance->context ?? [],
                'can_claim'
            );
            abort_unless($eligible->contains(fn ($candidate) =>
                $this->type($candidate) === $this->type($user)
                && (string) $candidate->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            ), 403, 'You are not permitted to attend this workflow level.');

            abort_unless(
                $this->participation->can($user, $track->level, 'can_claim', $instance->context ?? []),
                403,
                'You are not permitted to claim this workflow level.'
            );

            $track->update([
                'assigned_to_type' => $this->type($user),
                'assigned_to_id' => $user->getAuthIdentifier(),
            ]);

            WorkflowInboxItem::query()
                ->where('workflow_instance_level_id', $track->id)
                ->where(fn ($query) => $query
                    ->where('recipient_type', '!=', $this->type($user))
                    ->orWhere('recipient_id', '!=', $user->getAuthIdentifier()))
                ->whereNull('ended_at')
                ->update([
                    'status' => WorkflowInboxItem::ENDED,
                    'ended_at' => now(),
                    'updated_at' => now(),
                ]);

            WorkflowInboxItem::query()
                ->where('workflow_instance_level_id', $track->id)
                ->where('recipient_type', $this->type($user))
                ->where('recipient_id', $user->getAuthIdentifier())
                ->update([
                    'status' => WorkflowInboxItem::ATTENDED,
                    'opened_at' => now(),
                    'updated_at' => now(),
                ]);

            $result = $track->fresh(['level', 'instance']);
            $this->db->connection()->afterCommit(
                fn () => event(new WorkflowClaimed($instance->fresh(), $user, null, [
                    'workflow_instance_level_id' => $result->id,
                ]))
            );

            return $result;
        });
    }

    public function canClaim(WorkflowInstance $instance, Authenticatable $user): bool
    {
        $track = $instance->history()
            ->with('level.assignments')
            ->whereNull('exited_at')
            ->latest('id')
            ->first();

        if (!$track || $track->level->assignment_mode !== 'pooled' || $track->assigned_to_id) {
            return false;
        }

        if (!$this->participation->can($user, $track->level, 'can_claim', $instance->context ?? [])) {
            return false;
        }

        return $this->participation
            ->eligibleUsers($track->level, $instance->context ?? [], 'can_claim')
            ->contains(fn ($candidate) =>
                $this->type($candidate) === $this->type($user)
                && (string) $candidate->getAuthIdentifier() === (string) $user->getAuthIdentifier()
            );
    }

    public function markOpened(WorkflowInstance $instance, Authenticatable $user): void
    {
        $this->recipientQuery($user)
            ->where('workflow_instance_id', $instance->id)
            ->whereNull('ended_at')
            ->whereNull('responded_at')
            ->update([
                'status' => WorkflowInboxItem::ATTENDED,
                'opened_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function canView(WorkflowInstance $instance, Authenticatable $user): bool
    {
        if ($instance->initiator_type === $this->type($user)
            && (string) $instance->initiator_id === (string) $user->getAuthIdentifier()) {
            return true;
        }

        return $this->recipientQuery($user)
            ->where('workflow_instance_id', $instance->id)
            ->exists();
    }

    public function closeLevel(
        WorkflowInstanceLevel $instanceLevel,
        Authenticatable $actor,
        WorkflowAction $action
    ): void {
        WorkflowInboxItem::query()
            ->where('workflow_instance_level_id', $instanceLevel->id)
            ->whereNull('ended_at')
            ->update([
                'status' => WorkflowInboxItem::ENDED,
                'ended_at' => now(),
                'updated_at' => now(),
            ]);

        WorkflowInboxItem::updateOrCreate([
            'workflow_instance_level_id' => $instanceLevel->id,
            'recipient_type' => $this->type($actor),
            'recipient_id' => $actor->getAuthIdentifier(),
        ], [
                'workflow_instance_id' => $instanceLevel->workflow_instance_id,
                'status' => WorkflowInboxItem::RESPONDED,
                'opened_at' => now(),
                'responded_at' => now(),
                'ended_at' => now(),
                'workflow_action_id' => $action->id,
            ]);
    }

    public function recordResponse(
        WorkflowInstanceLevel $instanceLevel,
        Authenticatable $actor,
        WorkflowAction $action
    ): void {
        WorkflowInboxItem::updateOrCreate([
            'workflow_instance_level_id' => $instanceLevel->id,
            'recipient_type' => $this->type($actor),
            'recipient_id' => $actor->getAuthIdentifier(),
        ], [
            'workflow_instance_id' => $instanceLevel->workflow_instance_id,
            'status' => WorkflowInboxItem::RESPONDED,
            'opened_at' => now(),
            'responded_at' => now(),
            'workflow_action_id' => $action->id,
        ]);
    }

    public function closeInstance(WorkflowInstance $instance): void
    {
        $instance->inboxItems()
            ->whereNull('ended_at')
            ->update([
                'status' => WorkflowInboxItem::ENDED,
                'ended_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function counts(Authenticatable $user): array
    {
        return collect(['new', 'pending', 'attended', 'responded', 'held', 'ended'])
            ->mapWithKeys(fn (string $category) => [
                $category => $this->categoryQuery($user, $category)
                    ->distinct()
                    ->count('workflow_instance_id'),
            ])
            ->all();
    }

    public function items(Authenticatable $user, string $category = 'pending'): Collection
    {
        return $this->categoryQuery($user, $category)
            ->with([
                'instance.workflow.module',
                'instance.currentLevel',
                'instance.subject',
                'instanceLevel.level',
            ])
            ->latest('workflow_inbox_items.id')
            ->get()
            ->unique('workflow_instance_id')
            ->values();
    }

    public function categoryQuery(Authenticatable $user, string $category): Builder
    {
        $query = $this->recipientQuery($user);

        return match ($category) {
            'new' => $query->where('status', WorkflowInboxItem::NEW)
                ->whereNull('opened_at')
                ->whereNull('responded_at')
                ->whereNull('ended_at'),
            'pending' => $query->whereNull('responded_at')->whereNull('ended_at'),
            'attended' => $query->whereNotNull('opened_at')
                ->whereNull('responded_at')
                ->whereNull('ended_at'),
            'responded' => $query->whereNotNull('responded_at'),
            'held' => $query->whereHas(
                'instance',
                fn (Builder $instance) => $instance->where('status', WorkflowStatuses::ON_HOLD)
            ),
            'ended' => $query->whereHas(
                'instance',
                fn (Builder $instance) => $instance->whereNotNull('completed_at')
            ),
            default => $query->whereNull('responded_at')->whereNull('ended_at'),
        };
    }

    protected function recipientQuery(Authenticatable $user): Builder
    {
        return WorkflowInboxItem::query()
            ->where('recipient_type', $this->type($user))
            ->where('recipient_id', $user->getAuthIdentifier());
    }

    protected function type(object $model): string
    {
        return method_exists($model, 'getMorphClass') ? $model->getMorphClass() : get_class($model);
    }
}
