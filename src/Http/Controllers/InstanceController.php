<?php

namespace Qnox\Workflows\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Validation\Rule;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Services\{WorkflowEngine, WorkflowInbox};

class InstanceController extends Controller
{
    public function __construct(protected WorkflowEngine $engine, protected WorkflowInbox $inbox) {}
    public function show(WorkflowInstance $instance)
    {
        abort_unless($this->inbox->canView($instance, request()->user()), 403);
        $this->inbox->markOpened($instance, request()->user());
        return view(config('workflows.views.instance'), ['instance' => $instance->load(['workflow', 'currentLevel', 'history.approvers', 'actions']), 'actions' => $this->engine->availableActions($instance, request()->user())]);
    }
    public function actions(WorkflowInstance $instance) { return response()->json($this->engine->availableActions($instance, request()->user())); }
    public function decide(Request $request, WorkflowInstance $instance)
    {
        $data = $request->validate(['action' => ['required', Rule::in(['approve', 'reject'])], 'comment' => ['nullable', 'string', 'max:5000']]);
        $updated = $data['action'] === 'approve'
            ? $this->engine->approve($instance, $request->user(), $data['comment'] ?? null)
            : $this->engine->reject($instance, $request->user(), $data['comment'] ?? null);
        if ($request->expectsJson()) return response()->json($updated->only(['id', 'status', 'current_level_id', 'approved_at', 'rejected_at']));
        return back()->with('workflow_status', 'Workflow decision recorded.');
    }
}
