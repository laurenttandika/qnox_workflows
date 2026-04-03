<?php

namespace Qnox\Workflows\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Services\WorkflowEngine;

class InstanceController extends Controller
{
    public function __construct(protected WorkflowEngine $engine) {}

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
        return response()->json($updated->only([
            'id',
            'status',
            'current_level_id',
            'submitted_at',
            'completed_at',
            'last_action_at',
        ]));
    }
}
