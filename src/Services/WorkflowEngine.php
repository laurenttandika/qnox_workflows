<?php

namespace Qnox\Workflows\Services;

use Illuminate\Contracts\Auth\Authenticatable as User;
use Illuminate\Database\DatabaseManager as DB;
use Illuminate\Support\Arr;
use Qnox\Workflows\Contracts\AssignmentResolver;
use Qnox\Workflows\Models\{Workflow, WorkflowAction, WorkflowInstance, WorkflowInstanceLevel, WorkflowLevel};
use Qnox\Workflows\Services\Guards\GuardEvaluator;
use Qnox\Workflows\Notifications\NextApproverNotification;

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

        return $this->db->transaction(function () use ($subject, $workflow, $start, $context) {
            $instance = WorkflowInstance::create([
                'workflow_id' => $workflow->id,
                'subject_type' => get_class($subject),
                'subject_id' => $subject->getKey(),
                'current_level_id' => $start->id,
                'status' => 'in_progress',
                'context' => $context,
            ]);

            $instance->history()->create([
                'workflow_level_id' => $start->id,
                'entered_at' => now(),
            ]);

            $this->notifyNextApprovers($instance, $start);

            return $instance->fresh(['currentLevel', 'workflow']);
        });
    }

    /** @return array<int,array{action_key:string,label:string,to_level_id:int,direction:string,form_schema:array|null}> */
    public function availableActions(WorkflowInstance $instance, User $actor): array
    {
        $level = $instance->currentLevel()->with(['outgoingTransitions'])->firstOrFail();

        if (!$this->resolver->userEligibleForLevel($actor, $level, $instance->context ?? [])) {
            return [];
        }

        $transitions = $level->outgoingTransitions;
        $filtered = $transitions->filter(function ($t) use ($instance, $actor) {
            return $this->guards->passes($t->guard, $instance, $actor);
        });

        return $filtered->map(fn($t) => [
            'action_key' => $t->action_key,
            'label' => $t->label ?: ucfirst(str_replace('_', ' ', $t->action_key)),
            'to_level_id' => (int)$t->to_level_id,
            'direction' => $t->direction,
            'form_schema' => $t->form_schema,
        ])->values()->all();
    }

    public function act(WorkflowInstance $instance, string $actionKey, User $actor, array $payload = []): WorkflowInstance
    {
        return $this->db->transaction(function () use ($instance, $actionKey, $actor, $payload) {
            /** @var WorkflowLevel $current */
            $current = WorkflowLevel::query()->lockForUpdate()->findOrFail($instance->current_level_id);

            if (!$this->resolver->userEligibleForLevel($actor, $current, $instance->context ?? [])) {
                abort(403, 'Not eligible for this level.');
            }

            $transition = $current->outgoingTransitions()
                ->where('action_key', $actionKey)
                ->firstOrFail();

            if (!$this->guards->passes($transition->guard, $instance, $actor, $payload)) {
                abort(422, 'Transition guard failed.');
            }

            $to = $transition->toLevel()->firstOrFail();

            WorkflowAction::create([
                'workflow_instance_id' => $instance->id,
                'from_level_id' => $current->id,
                'to_level_id' => $to->id,
                'actor_type' => get_class($actor),
                'actor_id' => $actor->getAuthIdentifier(),
                'action_key' => $actionKey,
                'comment' => $payload['comment'] ?? null,
                'payload' => $payload,
            ]);

            $instance->history()
                ->whereNull('exited_at')
                ->where('workflow_level_id', $current->id)
                ->update(['exited_at' => now()]);

            $instance->update(['current_level_id' => $to->id]);

            $instance->history()->create([
                'workflow_level_id' => $to->id,
                'entered_at' => now(),
            ]);

            $this->notifyNextApprovers($instance->fresh(['currentLevel', 'workflow']), $to);

            if ($to->is_terminal) {
                $instance->update(['status' => 'completed']);
            }

            return $instance->fresh(['currentLevel', 'workflow']);
        });
    }

    public function notifyNextApprovers(WorkflowInstance $instance, WorkflowLevel $level): void
    {
        $assignees = $this->resolver->resolveAssignees($level, $instance->context ?? []);
        foreach ($assignees as $notifiable) {
            $notifiable->notify(new NextApproverNotification($instance));
        }
    }
}
