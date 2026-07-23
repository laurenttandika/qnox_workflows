<?php

namespace Qnox\Workflows\Http\Controllers;

use Illuminate\Routing\Controller;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Services\WorkflowEngine;
use Qnox\Workflows\Services\WorkflowInbox;

class InboxController extends Controller
{
    public function __construct(
        protected WorkflowInbox $inbox,
        protected WorkflowEngine $engine,
    ) {}

    public function index(?string $category = null)
    {
        $category = $category ?: 'pending';
        abort_unless(in_array($category, $this->categories(), true), 404);
        $user = request()->user();

        return view(config('workflows.views.inbox'), [
            'category' => $category,
            'categories' => $this->categories(),
            'counts' => $this->inbox->counts($user),
            'items' => $this->inbox->items($user, $category),
        ]);
    }

    public function show(WorkflowInstance $instance)
    {
        abort_unless($this->inbox->canView($instance, request()->user()), 403);
        $this->inbox->markOpened($instance, request()->user());
        $instance->load(['workflow.module', 'currentLevel', 'history.level', 'actions']);

        return view(config('workflows.views.instance'), [
            'instance' => $instance,
            'actions' => $this->engine->availableActions($instance, request()->user()),
            'canClaim' => $this->inbox->canClaim($instance, request()->user()),
        ]);
    }

    public function claim(WorkflowInstance $instance)
    {
        $this->inbox->claim($instance, request()->user());

        return back()->with('workflow_status', 'Workflow item attended and assigned to you.');
    }

    public function counts()
    {
        return response()->json($this->inbox->counts(request()->user()));
    }

    protected function categories(): array
    {
        return ['new', 'pending', 'attended', 'responded', 'held', 'ended'];
    }
}
