<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Database\DatabaseManager as DB;
use Qnox\Workflows\Contracts\AssignmentResolver;
use Qnox\Workflows\Models\Workflow;
use Qnox\Workflows\Models\WorkflowAction;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Models\WorkflowInstanceLevel;
use Qnox\Workflows\Models\WorkflowLevel;
use Qnox\Workflows\Models\WorkflowTransition;
use Qnox\Workflows\Services\Guards\GuardEvaluator;
use Qnox\Workflows\Support\WorkflowStatuses;

class WorkflowEngine
{
    public function __construct(
        protected DB $db,
        protected AssignmentResolver $resolver,
        protected GuardEvaluator $guards,
    ) {}

    public function start($subject, Workflow $workflow, User $initiator, array $context = []): WorkflowInstance
    {
        $start = $workflow->startLevel() ?? $workflow->levels()->orderBy('sequence')->firstOrFail();
        $context = $this->normalizeContext($context, $initiator);

        return $this->db->transaction(function () use ($subject, $workflow, $start, $initiator, $context) {
            $instance = WorkflowInstance::create([
                'workflow_id' => $workflow->id,
                'subject_type' => get_class($subject),
                'subject_id' => $subject->getKey(),
                'initiator_type' => get_class($initiator),
                'initiator_id' => $initiator->getAuthIdentifier(),
                'current_level_id' => $start->id,
                'status' => WorkflowStatuses::PENDING,
                'context' => $context,
            ]);

            $this->createHistoryEntry($instance, $start, WorkflowStatuses::PENDING);
            $this->notifyNextApprovers($instance, $start);

            return $instance->fresh(['currentLevel', 'workflow', 'history.level']);
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

        if (!$this->resolver->userEligibleForLevel($actor, $level, $instance->context ?? [])) {
            return [];
        }

        if ($level->outgoingTransitions->isNotEmpty()) {
            return $level->outgoingTransitions
                ->filter(fn (WorkflowTransition $transition) => $this->guards->passes($transition->guard, $instance, $actor))
                ->map(fn (WorkflowTransition $transition) => [
                    'action_key' => $transition->action_key,
                    'label' => $transition->label ?: $this->labelFor($transition->action_key),
                    'to_level_id' => (int) $transition->to_level_id,
                    'direction' => $transition->direction,
                    'status' => $this->statusForAction($transition->action_key, false),
                    'form_schema' => $transition->form_schema,
                ])
                ->values()
                ->all();
        }

        return collect($this->fallbackActions($instance, $level))
            ->map(fn (array $action) => [
                'action_key' => $action['action_key'],
                'label' => $this->labelFor($action['action_key']),
                'to_level_id' => $action['to_level_id'],
                'direction' => $action['direction'],
                'status' => $action['status'],
                'form_schema' => null,
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

            if (!$this->resolver->userEligibleForLevel($actor, $current, $instance->context ?? [])) {
                abort(403, 'Not eligible for this level.');
            }

            $transition = $current->outgoingTransitions
                ->firstWhere('action_key', $actionKey);

            if ($transition && !$this->guards->passes($transition->guard, $instance, $actor, $payload)) {
                abort(422, 'Transition guard failed.');
            }

            $resolution = $transition
                ? $this->resolveTransitionAction($instance, $current, $transition, $actionKey)
                : $this->resolveFallbackAction($instance, $current, $actionKey, $payload);

            if ($resolution === null) {
                abort(422, 'Action is not available for the current workflow level.');
            }

            $currentHistory = $this->currentHistory($instance, $current);
            if ($currentHistory) {
                $currentHistory->update([
                    'exited_at' => now(),
                    'forward_date' => now(),
                ]);
            }

            WorkflowAction::create([
                'workflow_instance_id' => $instance->id,
                'from_level_id' => $current->id,
                'to_level_id' => $resolution['to_level']?->id,
                'actor_type' => get_class($actor),
                'actor_id' => $actor->getAuthIdentifier(),
                'action_key' => $actionKey,
                'status' => $resolution['instance_status'],
                'comment' => $payload['comment'] ?? $payload['comments'] ?? null,
                'payload' => $payload,
            ]);

            $instanceUpdates = [
                'status' => $resolution['instance_status'],
                'current_level_id' => $resolution['to_level']?->id ?? $current->id,
                'last_action_at' => now(),
            ];

            if ($actionKey === 'submit' && !$instance->submitted_at) {
                $instanceUpdates['submitted_at'] = now();
            }

            if ($resolution['instance_status'] === WorkflowStatuses::COMPLETED) {
                $instanceUpdates['completed_at'] = now();
            }

            $instance->update($instanceUpdates);

            if ($resolution['to_level']) {
                $this->createHistoryEntry(
                    $instance->fresh(),
                    $resolution['to_level'],
                    $resolution['history_status'],
                    $currentHistory,
                    $actionKey,
                    $payload['comment'] ?? $payload['comments'] ?? null
                );

                if (!in_array($resolution['instance_status'], [WorkflowStatuses::COMPLETED, WorkflowStatuses::ON_HOLD], true)) {
                    $this->notifyNextApprovers($instance->fresh(['currentLevel', 'workflow']), $resolution['to_level']);
                }
            } elseif ($currentHistory) {
                $currentHistory->update([
                    'status' => $resolution['history_status'],
                    'action_key' => $actionKey,
                    'comments' => $payload['comment'] ?? $payload['comments'] ?? null,
                ]);
            }

            return $instance->fresh(['currentLevel', 'workflow', 'history.level', 'actions']);
        });
    }

    public function notifyNextApprovers(WorkflowInstance $instance, WorkflowLevel $level): void
    {
        $assignees = $this->resolver->resolveAssignees($level, $instance->context ?? []);

        foreach ($assignees as $notifiable) {
            $notifiable->notify(new \Qnox\Workflows\Notifications\NextApproverNotification($instance->fresh(['currentLevel'])));
        }
    }

    protected function normalizeContext(array $context, User $initiator): array
    {
        return array_replace_recursive($context, [
            'initiator' => [
                'id' => $initiator->getAuthIdentifier(),
                'type' => get_class($initiator),
            ],
        ]);
    }

    protected function fallbackActions(WorkflowInstance $instance, WorkflowLevel $level): array
    {
        $status = $instance->status;
        $next = $this->nextLevel($level);
        $previous = $this->previousLevel($level);
        $actions = [];

        if ($status === WorkflowStatuses::ON_HOLD) {
            return [[
                'action_key' => 'resume',
                'to_level_id' => $level->id,
                'direction' => 'stay',
                'status' => WorkflowStatuses::IN_PROGRESS,
            ]];
        }

        if ($level->is_start && $next) {
            $actions[] = [
                'action_key' => 'submit',
                'to_level_id' => $next->id,
                'direction' => 'forward',
                'status' => WorkflowStatuses::IN_PROGRESS,
            ];
        } elseif ($next) {
            $actions[] = [
                'action_key' => 'approve',
                'to_level_id' => $next->id,
                'direction' => 'forward',
                'status' => WorkflowStatuses::APPROVED,
            ];
        }

        if (($level->allow_rejection ?? true) && $previous) {
            $actions[] = [
                'action_key' => 'return',
                'to_level_id' => $previous->id,
                'direction' => 'backward',
                'status' => WorkflowStatuses::RETURNED,
            ];

            $actions[] = [
                'action_key' => 'reject',
                'to_level_id' => $previous->id,
                'direction' => 'backward',
                'status' => WorkflowStatuses::REJECTED,
            ];
        }

        if (!$level->is_terminal) {
            $actions[] = [
                'action_key' => 'hold',
                'to_level_id' => $level->id,
                'direction' => 'stay',
                'status' => WorkflowStatuses::ON_HOLD,
            ];
        }

        if ($instance->submitted_at && $level->sequence > 1) {
            $start = $instance->workflow->startLevel() ?? $instance->workflow->levels()->orderBy('sequence')->first();
            $actions[] = [
                'action_key' => 'recall',
                'to_level_id' => $start?->id,
                'direction' => 'backward',
                'status' => WorkflowStatuses::RECALLED,
            ];
        }

        if ($level->is_terminal || (!$next && ($level->can_close ?? true))) {
            $actions[] = [
                'action_key' => 'complete',
                'to_level_id' => null,
                'direction' => 'stay',
                'status' => WorkflowStatuses::COMPLETED,
            ];
        }

        return collect($actions)->unique('action_key')->values()->all();
    }

    protected function resolveTransitionAction(
        WorkflowInstance $instance,
        WorkflowLevel $current,
        WorkflowTransition $transition,
        string $actionKey
    ): array {
        $to = $transition->toLevel()->firstOrFail();
        $completes = $to->is_terminal || (!$this->nextLevel($to) && ($to->can_close ?? true) && in_array($actionKey, ['approve', 'complete'], true));

        return [
            'to_level' => $to,
            'instance_status' => $completes
                ? WorkflowStatuses::COMPLETED
                : $this->statusForAction($actionKey, false),
            'history_status' => $completes
                ? WorkflowStatuses::COMPLETED
                : $this->statusForAction($actionKey, false),
        ];
    }

    protected function resolveFallbackAction(
        WorkflowInstance $instance,
        WorkflowLevel $current,
        string $actionKey,
        array $payload = []
    ): ?array {
        $fallback = collect($this->fallbackActions($instance, $current))
            ->firstWhere('action_key', $actionKey);

        if (!$fallback) {
            return null;
        }

        $toLevel = $fallback['to_level_id']
            ? WorkflowLevel::query()->findOrFail($fallback['to_level_id'])
            : null;

        $instanceStatus = $fallback['status'];
        $historyStatus = $fallback['status'];

        if ($actionKey === 'submit') {
            $instanceStatus = WorkflowStatuses::IN_PROGRESS;
            $historyStatus = WorkflowStatuses::PENDING;
        }

        if ($actionKey === 'resume') {
            $instanceStatus = WorkflowStatuses::IN_PROGRESS;
            $historyStatus = WorkflowStatuses::PENDING;
        }

        if ($actionKey === 'approve' && $toLevel && ($toLevel->is_terminal || !$this->nextLevel($toLevel))) {
            $instanceStatus = WorkflowStatuses::COMPLETED;
            $historyStatus = WorkflowStatuses::COMPLETED;
        }

        if ($actionKey === 'complete') {
            $instanceStatus = WorkflowStatuses::COMPLETED;
            $historyStatus = WorkflowStatuses::COMPLETED;
            $toLevel = null;
        }

        if ($actionKey === 'hold') {
            $toLevel = $current;
        }

        return [
            'to_level' => $toLevel,
            'instance_status' => $instanceStatus,
            'history_status' => $historyStatus,
        ];
    }

    protected function createHistoryEntry(
        WorkflowInstance $instance,
        WorkflowLevel $level,
        string $status,
        ?WorkflowInstanceLevel $parent = null,
        ?string $actionKey = null,
        ?string $comments = null
    ): WorkflowInstanceLevel {
        $assignee = $this->resolver->resolveAssignees($level, $instance->context ?? [])->first();

        return $instance->history()->create([
            'workflow_level_id' => $level->id,
            'parent_id' => $parent?->id,
            'assigned_to_type' => $assignee ? get_class($assignee) : null,
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

    protected function nextLevel(WorkflowLevel $level): ?WorkflowLevel
    {
        return $level->workflow
            ? $level->workflow->levels()->where('sequence', '>', $level->sequence)->orderBy('sequence')->first()
            : WorkflowLevel::query()->where('workflow_id', $level->workflow_id)->where('sequence', '>', $level->sequence)->orderBy('sequence')->first();
    }

    protected function previousLevel(WorkflowLevel $level): ?WorkflowLevel
    {
        return $level->workflow
            ? $level->workflow->levels()->where('sequence', '<', $level->sequence)->orderByDesc('sequence')->first()
            : WorkflowLevel::query()->where('workflow_id', $level->workflow_id)->where('sequence', '<', $level->sequence)->orderByDesc('sequence')->first();
    }

    protected function labelFor(string $actionKey): string
    {
        return config('workflows.action_labels.' . $actionKey)
            ?: ucfirst(str_replace('_', ' ', $actionKey));
    }

    protected function statusForAction(string $actionKey, bool $sameLevel = false): string
    {
        return match ($actionKey) {
            'submit' => WorkflowStatuses::IN_PROGRESS,
            'approve' => WorkflowStatuses::APPROVED,
            'reject' => WorkflowStatuses::REJECTED,
            'return' => WorkflowStatuses::RETURNED,
            'hold' => WorkflowStatuses::ON_HOLD,
            'resume' => WorkflowStatuses::IN_PROGRESS,
            'recall' => WorkflowStatuses::RECALLED,
            'complete' => WorkflowStatuses::COMPLETED,
            default => $sameLevel ? WorkflowStatuses::PENDING : WorkflowStatuses::IN_PROGRESS,
        };
    }
}
