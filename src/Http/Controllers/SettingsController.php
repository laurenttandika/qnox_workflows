<?php

namespace Qnox\Workflows\Http\Controllers;

use Illuminate\Routing\Controller;
use Qnox\Workflows\Contracts\{ModuleRegistry, RoleProvider, UserProvider};
use Qnox\Workflows\Http\Requests\SaveWorkflowRequest;
use Qnox\Workflows\Models\Workflow;
use Qnox\Workflows\Services\WorkflowDefinitionService;

class SettingsController extends Controller
{
    public function __construct(protected ModuleRegistry $modules, protected RoleProvider $roles, protected UserProvider $users) {}

    public function modules()
    {
        $this->authorizeManage();
        return view(config('workflows.views.modules'), ['modules' => $this->modules->all()]);
    }

    public function definitions(string $module)
    {
        $this->authorizeManage();
        abort_unless($this->modules->has($module), 404);
        return view(config('workflows.views.definitions'), [
            'moduleKey' => $module, 'moduleLabel' => $this->modules->label($module),
            'workflows' => Workflow::where('module_key', $module)->withCount('levels')->orderBy('name')->get(),
        ]);
    }

    public function create(string $module)
    {
        $this->authorizeManage();
        abort_unless($this->modules->has($module), 404);
        return $this->editor(new Workflow(['module_key' => $module, 'is_active' => true]));
    }

    public function edit(Workflow $workflow)
    {
        $this->authorizeManage();
        return $this->editor($workflow->load(['levels' => fn ($q) => $q->with('selectedUsers')->orderBy('sequence')]));
    }

    public function store(SaveWorkflowRequest $request, WorkflowDefinitionService $service)
    {
        $workflow = $service->save($request->validated());
        return redirect()->route($this->route('definitions.edit'), $workflow)->with('workflow_status', 'Workflow created.');
    }

    public function update(SaveWorkflowRequest $request, Workflow $workflow, WorkflowDefinitionService $service)
    {
        $service->save($request->validated(), $workflow);
        return back()->with('workflow_status', 'Workflow saved.');
    }

    protected function editor(Workflow $workflow)
    {
        return view(config('workflows.views.definition'), [
            'workflow' => $workflow, 'moduleLabel' => $this->modules->label($workflow->module_key),
            'roles' => $this->roles->options(), 'users' => $this->users->options(),
        ]);
    }

    protected function authorizeManage(): void
    {
        $permission = config('workflows.permissions.manage');
        abort_unless(request()->user() && (!$permission || request()->user()->can($permission)), 403);
    }
    protected function route(string $name): string { return config('workflows.routes.web.name_prefix', 'workflows.').$name; }
}
