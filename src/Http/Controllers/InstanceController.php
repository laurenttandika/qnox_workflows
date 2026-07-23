<?php

namespace Qnox\Workflows\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Services\WorkflowEngine;
use Qnox\Workflows\Services\WorkflowInbox;

class InstanceController extends Controller
{
    public function __construct(
        protected WorkflowEngine $engine,
        protected WorkflowInbox $inbox,
    ) {}

    public function show(WorkflowInstance $instance)
    {
        abort_unless($this->inbox->canView($instance, request()->user()), 403);
        $this->inbox->markOpened($instance, request()->user());
        $instance->load(['workflow.module', 'currentLevel', 'history.level', 'actions']);

        return view(config('workflows.views.instance'), [
            'instance' => $instance,
            'actions' => $this->engine->availableActions($instance, request()->user()),
        ]);
    }

    public function actions(WorkflowInstance $instance)
    {
        $actions = $this->engine->availableActions($instance, request()->user());
        return response()->json($actions);
    }

    public function act(Request $request, WorkflowInstance $instance)
    {
        $data = $request->validate([
            'action_key' => ['required','string','max:64'],
            'payload' => ['array'],
        ]);

        $updated = $this->engine->act($instance, $data['action_key'], $request->user(), $data['payload'] ?? []);
        $response = $updated->only([
            'id',
            'status',
            'current_level_id',
            'submitted_at',
            'completed_at',
            'last_action_at',
        ]);

        if ($request->expectsJson()) {
            return response()->json($response);
        }

        return back()->with('workflow_status', 'Workflow action completed.');
    }
}
