<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Qnox\Workflows\Models\{WorkflowInboxItem, WorkflowInstance};

class WorkflowInbox
{
    public function pendingFor(Authenticatable $user): Collection { return $this->items($user, 'pending'); }
    public function markOpened(WorkflowInstance $instance, Authenticatable $user): void
    { $this->recipientQuery($user)->where('workflow_instance_id', $instance->id)->whereNull('ended_at')->update(['opened_at' => now(), 'updated_at' => now()]); }
    public function canView(WorkflowInstance $instance, Authenticatable $user): bool
    {
        return ($instance->initiator_type === $this->type($user) && (string) $instance->initiator_id === (string) $user->getAuthIdentifier())
            || $this->recipientQuery($user)->where('workflow_instance_id', $instance->id)->exists();
    }
    public function counts(Authenticatable $user): array
    {
        $query = $this->recipientQuery($user);
        return ['pending' => (clone $query)->whereNull('ended_at')->count(), 'responded' => (clone $query)->whereNotNull('responded_at')->count(), 'ended' => (clone $query)->whereNotNull('ended_at')->count()];
    }
    public function items(Authenticatable $user, string $category = 'pending'): Collection
    {
        $query = $this->recipientQuery($user)->with(['instance.workflow', 'instance.currentLevel', 'instance.subject', 'instanceLevel']);
        match ($category) {
            'responded' => $query->whereNotNull('responded_at'),
            'ended' => $query->whereNotNull('ended_at'),
            default => $query->whereNull('ended_at'),
        };
        return $query->latest()->get();
    }
    protected function recipientQuery(Authenticatable $user): Builder
    { return WorkflowInboxItem::query()->where('recipient_type', $this->type($user))->where('recipient_id', $user->getAuthIdentifier()); }
    protected function type(object $model): string { return method_exists($model, 'getMorphClass') ? $model->getMorphClass() : get_class($model); }
}
