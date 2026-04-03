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

            if (!$this->resolver->userEligibleForLevel($actor, $current, $instance->context ?? [])) {
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

            $resolution = $this->resolveTransitionAction($instance, $current, $transition, $actionKey);

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

            if (($transition->meta['mark_submitted'] ?? false) || ($actionKey === 'submit' && !$instance->submitted_at)) {
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
}
