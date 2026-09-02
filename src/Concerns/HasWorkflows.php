<?php

namespace Qnox\Workflows\Concerns;

use Illuminate\Contracts\Auth\Authenticatable;
use Qnox\Workflows\Models\Workflow;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Services\WorkflowEngine;

trait HasWorkflows
{
    public function workflowInstances()
    {
        return $this->morphMany(WorkflowInstance::class, 'subject');
    }

    public function currentWorkflowInstance(): ?WorkflowInstance
    {
        return $this->workflowInstances()
            ->whereIn('status', ['pending', 'in_progress'])
            ->latest('id')
            ->first();
    }

    public function startWorkflow(
        Workflow $workflow,
        Authenticatable $initiator,
        array $context = []
    ): WorkflowInstance {
        return app(WorkflowEngine::class)->start($this, $workflow, $initiator, $context);
    }
}
