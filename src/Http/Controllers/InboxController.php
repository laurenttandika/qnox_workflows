<?php
namespace Qnox\Workflows\Http\Controllers;
use Illuminate\Routing\Controller;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Services\{WorkflowEngine, WorkflowInbox};
class InboxController extends Controller
{
    public function __construct(protected WorkflowInbox $inbox, protected WorkflowEngine $engine) {}
    public function index() { $category = request('category', 'pending'); abort_unless(in_array($category, ['pending', 'responded', 'ended'], true), 404); return view(config('workflows.views.inbox'), ['category' => $category, 'categories' => ['pending', 'responded', 'ended'], 'counts' => $this->inbox->counts(request()->user()), 'items' => $this->inbox->items(request()->user(), $category)]); }
    public function show(WorkflowInstance $instance) { abort_unless($this->inbox->canView($instance, request()->user()), 403); $this->inbox->markOpened($instance, request()->user()); return view(config('workflows.views.instance'), ['instance' => $instance->load(['workflow', 'currentLevel', 'history.approvers', 'actions']), 'actions' => $this->engine->availableActions($instance, request()->user())]); }
    public function counts() { return response()->json($this->inbox->counts(request()->user())); }
}
