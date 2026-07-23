<?php

namespace Qnox\Workflows\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Qnox\Workflows\Models\NumberSequence;
use Qnox\Workflows\Models\Workflow;
use Qnox\Workflows\Models\WorkflowGroup;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Models\WorkflowModule;
use Qnox\Workflows\Models\WorkflowLevel;
use Qnox\Workflows\Models\WorkflowTransition;
use Qnox\Workflows\Models\WorkflowAssignment;
use Qnox\Workflows\Services\NumberGenerator;

class SettingsController extends Controller
{
    public function dashboard()
    {
        return view(config('workflows.views.dashboard'), [
            'counts' => [
                'groups' => WorkflowGroup::count(),
                'modules' => WorkflowModule::count(),
                'workflows' => Workflow::count(),
                'active_instances' => WorkflowInstance::whereNull('completed_at')->count(),
                'number_sequences' => NumberSequence::count(),
            ],
        ]);
    }

    public function groups()
    {
        return view(config('workflows.views.groups'), [
            'groups' => WorkflowGroup::withCount(['modules', 'workflows'])->orderBy('name')->get(),
        ]);
    }

    public function storeGroup(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:workflow_groups,slug'],
        ]);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        WorkflowGroup::create($data);

        return back()->with('workflow_status', 'Workflow group created.');
    }

    public function updateGroup(Request $request, WorkflowGroup $group)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['required', 'string', 'max:120', Rule::unique('workflow_groups')->ignore($group)],
        ]);
        $group->update($data);

        return back()->with('workflow_status', 'Workflow group updated.');
    }

    public function modules()
    {
        return view(config('workflows.views.modules'), [
            'groups' => WorkflowGroup::orderBy('name')->get(),
            'modules' => WorkflowModule::with('group')->withCount('workflows')->orderBy('name')->get(),
        ]);
    }

    public function storeModule(Request $request)
    {
        $data = $this->moduleData($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        WorkflowModule::create($data);

        return back()->with('workflow_status', 'Workflow module created.');
    }

    public function updateModule(Request $request, WorkflowModule $module)
    {
        $module->update($this->moduleData($request, $module));

        return back()->with('workflow_status', 'Workflow module updated.');
    }

    public function numbers()
    {
        return view(config('workflows.views.numbers'), [
            'sequences' => NumberSequence::orderBy('name')->get(),
        ]);
    }

    public function definitions()
    {
        return view(config('workflows.views.definitions'), [
            'groups' => WorkflowGroup::orderBy('name')->get(),
            'modules' => WorkflowModule::orderBy('name')->get(),
            'workflows' => Workflow::with(['group', 'module'])
                ->withCount('levels')
                ->orderBy('name')
                ->get(),
        ]);
    }

    public function storeDefinition(Request $request)
    {
        $data = $request->validate([
            'workflow_group_id' => ['required', 'exists:workflow_groups,id'],
            'workflow_module_id' => ['nullable', 'exists:workflow_modules,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', 'unique:workflows,slug'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => false];
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $workflow = Workflow::create($data);

        return redirect()->route(
            config('workflows.routes.web.name_prefix', 'workflows.').'definitions.show',
            $workflow
        )->with('workflow_status', 'Workflow definition created. Add its levels and transitions.');
    }

    public function definition(Workflow $workflow)
    {
        $workflow->load([
            'group',
            'module',
            'levels' => fn ($query) => $query->with('assignments')->orderBy('sequence'),
            'transitions.fromLevel',
            'transitions.toLevel',
        ]);

        return view(config('workflows.views.definition'), compact('workflow'));
    }

    public function storeLevel(Request $request, Workflow $workflow)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sequence' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('workflow_levels')->where('workflow_id', $workflow->id),
            ],
            'description' => ['nullable', 'string'],
            'is_start' => ['sometimes', 'boolean'],
            'is_terminal' => ['sometimes', 'boolean'],
            'is_approval' => ['sometimes', 'boolean'],
        ]) + ['is_start' => false, 'is_terminal' => false, 'is_approval' => false];

        if ($data['is_start']) {
            $workflow->levels()->update(['is_start' => false]);
        }

        $workflow->levels()->create($data);

        return back()->with('workflow_status', 'Workflow level added.');
    }

    public function storeAssignment(Request $request, Workflow $workflow)
    {
        $options = config('workflows.assignment_options', []);
        $data = $request->validate([
            'workflow_level_id' => [
                'required',
                Rule::exists('workflow_levels', 'id')->where('workflow_id', $workflow->id),
            ],
            'type' => ['required', Rule::in(array_keys($options))],
            'assignable_id' => ['nullable', 'integer'],
            'criteria' => ['nullable', 'json'],
        ]);

        $option = $options[$data['type']];
        $criteria = $data['criteria'] ? json_decode($data['criteria'], true, flags: JSON_THROW_ON_ERROR) : null;
        $modelClass = $option['model'];

        if (!$data['assignable_id'] && !$criteria) {
            return back()->withErrors([
                'assignable_id' => 'Provide an assignable ID or assignment criteria.',
            ])->withInput();
        }

        if ($data['assignable_id'] && (!$modelClass::query()->whereKey($data['assignable_id'])->exists())) {
            return back()->withErrors([
                'assignable_id' => "The selected {$option['label']} does not exist.",
            ])->withInput();
        }

        WorkflowAssignment::create([
            'workflow_level_id' => $data['workflow_level_id'],
            'type' => $data['type'],
            'assignable_type' => $data['assignable_id'] ? (new $modelClass)->getMorphClass() : null,
            'assignable_id' => $data['assignable_id'] ?: null,
            'criteria' => $criteria,
        ]);

        return back()->with('workflow_status', 'Level assignment added.');
    }

    public function storeTransition(Request $request, Workflow $workflow)
    {
        $data = $request->validate([
            'from_level_id' => [
                'required',
                Rule::exists('workflow_levels', 'id')->where('workflow_id', $workflow->id),
            ],
            'to_level_id' => [
                'nullable',
                Rule::exists('workflow_levels', 'id')->where('workflow_id', $workflow->id),
            ],
            'action_key' => ['required', 'alpha_dash', 'max:64'],
            'label' => ['required', 'string', 'max:120'],
            'direction' => ['required', Rule::in(['forward', 'backward', 'stay'])],
            'status' => ['required', 'string', 'max:64'],
            'complete' => ['sometimes', 'boolean'],
        ]);
        $data['workflow_id'] = $workflow->id;
        $data['meta'] = ['complete' => (bool) ($data['complete'] ?? false)];
        unset($data['complete']);

        WorkflowTransition::create($data);

        return back()->with('workflow_status', 'Workflow transition added.');
    }

    public function storeNumber(Request $request)
    {
        NumberSequence::create($this->numberData($request));

        return back()->with('workflow_status', 'Number format created.');
    }

    public function updateNumber(Request $request, NumberSequence $sequence)
    {
        $sequence->update($this->numberData($request, $sequence));

        return back()->with('workflow_status', 'Number format updated.');
    }

    public function previewNumber(NumberSequence $sequence, NumberGenerator $generator)
    {
        return response()->json([
            'preview' => $generator->preview($sequence->key, request()->query()),
        ]);
    }

    protected function moduleData(Request $request, ?WorkflowModule $module = null): array
    {
        return $request->validate([
            'workflow_group_id' => ['required', 'exists:workflow_groups,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                $module ? 'required' : 'nullable',
                'string',
                'max:120',
                Rule::unique('workflow_modules')->ignore($module),
            ],
            'handler' => ['nullable', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => false];
    }

    protected function numberData(Request $request, ?NumberSequence $sequence = null): array
    {
        return $request->validate([
            'key' => ['required', 'string', 'max:120', Rule::unique('workflow_number_sequences')->ignore($sequence)],
            'name' => ['required', 'string', 'max:120'],
            'format' => ['required', 'string', 'max:255', function ($attribute, $value, $fail) {
                preg_match_all('/\{([a-z_]+)(?::\d+)?\}/i', $value, $matches);
                $invalid = array_diff($matches[1], config('workflows.numbering.allowed_tokens', []));
                if ($invalid) {
                    $fail('Unsupported number tokens: '.implode(', ', $invalid));
                }
                if (!in_array('number', $matches[1], true)) {
                    $fail('The format must contain the {number} token.');
                }
            }],
            'prefix' => ['nullable', 'string', 'max:100'],
            'next_value' => ['required', 'integer', 'min:1'],
            'padding' => ['required', 'integer', 'between:1,20'],
            'reset_period' => ['required', Rule::in(['never', 'yearly', 'monthly', 'daily'])],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => false];
    }
}
