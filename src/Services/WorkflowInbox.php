<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Qnox\Workflows\Contracts\AssignmentResolver;
use Qnox\Workflows\Models\WorkflowAction;
use Qnox\Workflows\Models\WorkflowInboxItem;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Models\WorkflowInstanceLevel;
use Qnox\Workflows\Models\WorkflowLevel;
use Qnox\Workflows\Support\WorkflowStatuses;

class WorkflowInbox
{
    public function __construct(protected AssignmentResolver $resolver) {}

    public function createForLevel(
        WorkflowInstance $instance,
        WorkflowInstanceLevel $instanceLevel,
        WorkflowLevel $level
    ): Collection {
        return $this->resolver
            ->resolveAssignees($level->loadMissing('assignments'), $instance->context ?? [])
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
