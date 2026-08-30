<?php

namespace Qnox\Workflows\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Qnox\Workflows\Models\NumberSequence;
use Qnox\Workflows\Models\Workflow;
use Qnox\Workflows\Models\WorkflowGroup;
use Qnox\Workflows\Models\WorkflowInstance;
use Qnox\Workflows\Models\WorkflowModule;
use Qnox\Workflows\Models\WorkflowLevel;
use Qnox\Workflows\Models\WorkflowTransition;
use Qnox\Workflows\Models\WorkflowAssignment;
use Qnox\Workflows\Models\WorkflowLevelParticipant;
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
        $data = $this->definitionData($request);
        $data['slug'] = $data['slug'] ?: Str::slug($data['name']);
        $workflow = Workflow::create($data);

        return redirect()->route(
            config('workflows.routes.web.name_prefix', 'workflows.').'definitions.show',
            $workflow
        )->with('workflow_status', 'Workflow definition created. Add its levels and transitions.');
    }

    public function updateDefinition(Request $request, Workflow $workflow)
    {
        $workflow->update($this->definitionData($request, $workflow));

        return back()->with('workflow_status', 'Workflow definition updated.');
    }

    protected function definitionData(Request $request, ?Workflow $workflow = null): array
    {
        return $request->validate([
            'workflow_group_id' => ['required', 'exists:workflow_groups,id'],
            'workflow_module_id' => ['nullable', 'exists:workflow_modules,id'],
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                $workflow ? 'required' : 'nullable',
                'string',
                'max:120',
                Rule::unique('workflows')->ignore($workflow),
            ],
            'is_active' => ['sometimes', 'boolean'],
        ]) + ['is_active' => false];
    }

    public function definition(Workflow $workflow)
    {
        $workflow->load([
            'group',
            'module',
            'levels' => fn ($query) => $query->with(['assignments', 'participants.participant'])->orderBy('sequence'),
            'transitions.fromLevel',
            'transitions.toLevel',
        ]);

        return view(config('workflows.views.definition'), [
            'workflow' => $workflow,
            'groups' => WorkflowGroup::orderBy('name')->get(),
            'modules' => WorkflowModule::orderBy('name')->get(),
        ]);
    }

    public function storeLevel(Request $request, Workflow $workflow)
    {
        $data = $this->levelData($request, $workflow);

        if ($data['is_start']) {
            $workflow->levels()->update(['is_start' => false]);
        }

        $workflow->levels()->create($data);

        return back()->with('workflow_status', 'Workflow level added.');
    }

    public function updateLevel(Request $request, Workflow $workflow, WorkflowLevel $level)
    {
        abort_unless((int) $level->workflow_id === (int) $workflow->id, 404);
        $data = $this->levelData($request, $workflow, $level);

        if ($data['is_start']) {
            $workflow->levels()->where('id', '!=', $level->id)->update(['is_start' => false]);
        }

        $level->update($data);

        return back()->with('workflow_status', 'Workflow level updated.');
    }

    protected function levelData(
        Request $request,
        Workflow $workflow,
        ?WorkflowLevel $level = null
    ): array {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'sequence' => [
                'required',
                'integer',
                'min:1',
                Rule::unique('workflow_levels')
                    ->where('workflow_id', $workflow->id)
                    ->ignore($level),
            ],
            'assignment_mode' => ['required', Rule::in(['pooled', 'automatic', 'direct'])],
            'description' => ['nullable', 'string'],
            'is_start' => ['sometimes', 'boolean'],
            'is_terminal' => ['sometimes', 'boolean'],
            'is_approval' => ['sometimes', 'boolean'],
            'is_optional' => ['sometimes', 'boolean'],
            'allow_rejection' => ['sometimes', 'boolean'],
            'allow_repeat_participate' => ['sometimes', 'boolean'],
            'allow_round_robin' => ['sometimes', 'boolean'],
            'can_close' => ['sometimes', 'boolean'],
            'msg_next' => ['nullable', 'string'],
            'action_description' => ['nullable', 'string', 'max:120'],
            'status_description' => ['nullable', 'string', 'max:120'],
            'rules' => ['nullable', 'json'],
        ]) + [
            'is_start' => false,
            'is_terminal' => false,
            'is_approval' => false,
            'is_optional' => false,
            'allow_rejection' => false,
            'allow_repeat_participate' => false,
            'allow_round_robin' => false,
            'can_close' => false,
        ];

        $data['rules'] = $data['rules'] ? json_decode($data['rules'], true, flags: JSON_THROW_ON_ERROR) : null;

        return $data;
    }

    public function storeParticipant(Request $request, Workflow $workflow)
    {
        $options = config('workflows.participant_options', []);
        $data = $request->validate([
            'workflow_level_id' => [
                'required',
                Rule::exists('workflow_levels', 'id')->where('workflow_id', $workflow->id),
            ],
            'type' => ['required', Rule::in(array_keys($options))],
            'participant_id' => ['required', 'integer'],
            'role' => ['nullable', 'string', 'max:80'],
            'can_view' => ['sometimes', 'boolean'],
            'can_claim' => ['sometimes', 'boolean'],
            'can_act' => ['sometimes', 'boolean'],
        ]) + ['can_view' => false, 'can_claim' => false, 'can_act' => false];

        $option = $options[$data['type']];
        $modelClass = $option['model'];
        abort_unless($modelClass::query()->whereKey($data['participant_id'])->exists(), 422, 'Participant not found.');

        WorkflowLevelParticipant::updateOrCreate([
            'workflow_level_id' => $data['workflow_level_id'],
            'participant_type' => (new $modelClass)->getMorphClass(),
            'participant_id' => $data['participant_id'],
        ], [
            'type' => $data['type'],
            'role' => $data['role'] ?? null,
            'can_view' => $data['can_view'],
            'can_claim' => $data['can_claim'],
            'can_act' => $data['can_act'],
        ]);

        return back()->with('workflow_status', 'Workflow participant permission saved.');
    }

    public function updateParticipant(
        Request $request,
        Workflow $workflow,
        WorkflowLevelParticipant $participant
    ) {
        abort_unless((int) $participant->level?->workflow_id === (int) $workflow->id, 404);
        $data = $request->validate([
            'workflow_level_id' => [
                'required',
                Rule::exists('workflow_levels', 'id')->where('workflow_id', $workflow->id),
            ],
            'role' => ['nullable', 'string', 'max:80'],
            'can_view' => ['sometimes', 'boolean'],
            'can_claim' => ['sometimes', 'boolean'],
            'can_act' => ['sometimes', 'boolean'],
        ]) + ['can_view' => false, 'can_claim' => false, 'can_act' => false];
        $participant->update($data);

        return back()->with('workflow_status', 'Workflow participant updated.');
    }

    public function participants()
    {
        $userClass = config('workflows.user_model');

        return view(config('workflows.views.participants'), [
            'users' => $userClass::query()->orderBy('id')->limit(100)->get(),
        ]);
    }

    public function userPermissions(int|string $user)
    {
        $userClass = config('workflows.user_model');
        $user = $userClass::query()->findOrFail($user);
        $morphType = $user->getMorphClass();

        return view(config('workflows.views.user_permissions'), [
            'user' => $user,
            'workflows' => Workflow::with([
                'group',
                'module',
                'levels' => fn ($query) => $query->orderBy('sequence'),
            ])->orderBy('name')->get(),
            'selected' => WorkflowLevelParticipant::query()
                ->where('participant_type', $morphType)
                ->where('participant_id', $user->getAuthIdentifier())
                ->pluck('workflow_level_id')
                ->map(fn ($id) => (int) $id)
                ->all(),
        ]);
    }

    public function updateUserPermissions(Request $request, int|string $user)
    {
        $userClass = config('workflows.user_model');
        $user = $userClass::query()->findOrFail($user);
        $data = $request->validate([
            'level_ids' => ['array'],
            'level_ids.*' => ['integer', 'exists:workflow_levels,id'],
        ]);
        $levelIds = collect($data['level_ids'] ?? [])->map(fn ($id) => (int) $id);
        $morphType = $user->getMorphClass();

        WorkflowLevelParticipant::query()
            ->where('type', 'user')
            ->where('participant_type', $morphType)
            ->where('participant_id', $user->getAuthIdentifier())
            ->whereNotIn('workflow_level_id', $levelIds)
            ->delete();

        foreach ($levelIds as $levelId) {
            WorkflowLevelParticipant::updateOrCreate([
                'workflow_level_id' => $levelId,
                'participant_type' => $morphType,
                'participant_id' => $user->getAuthIdentifier(),
            ], [
                'type' => 'user',
                'can_view' => true,
                'can_claim' => true,
                'can_act' => true,
            ]);
        }

        return back()->with('workflow_status', 'User workflow permissions updated.');
    }

    public function storeAssignment(Request $request, Workflow $workflow)
    {
        $data = $this->assignmentData($request, $workflow);
        WorkflowAssignment::create($data);

        return back()->with('workflow_status', 'Level assignment added.');
    }

    public function updateAssignment(Request $request, Workflow $workflow, WorkflowAssignment $assignment)
    {
        abort_unless((int) $assignment->level?->workflow_id === (int) $workflow->id, 404);
        $assignment->update($this->assignmentData($request, $workflow));

        return back()->with('workflow_status', 'Level assignment updated.');
    }

    protected function assignmentData(Request $request, Workflow $workflow): array
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
            throw ValidationException::withMessages([
                'assignable_id' => 'Provide an assignable ID or assignment criteria.',
            ]);
        }

        if ($data['assignable_id'] && (!$modelClass::query()->whereKey($data['assignable_id'])->exists())) {
            throw ValidationException::withMessages([
                'assignable_id' => "The selected {$option['label']} does not exist.",
            ]);
        }

        return [
            'workflow_level_id' => $data['workflow_level_id'],
            'type' => $data['type'],
            'assignable_type' => $data['assignable_id'] ? (new $modelClass)->getMorphClass() : null,
            'assignable_id' => $data['assignable_id'] ?: null,
            'criteria' => $criteria,
        ];
    }

    public function storeTransition(Request $request, Workflow $workflow)
    {
        WorkflowTransition::create($this->transitionData($request, $workflow));

        return back()->with('workflow_status', 'Workflow transition added.');
    }

    public function updateTransition(Request $request, Workflow $workflow, WorkflowTransition $transition)
    {
        abort_unless((int) $transition->workflow_id === (int) $workflow->id, 404);
        $transition->update($this->transitionData($request, $workflow, $transition));

        return back()->with('workflow_status', 'Workflow transition updated.');
    }

    protected function transitionData(Request $request, Workflow $workflow, ?WorkflowTransition $transition = null): array
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
            'action_key' => [
                'required',
                'alpha_dash',
                'max:64',
                Rule::unique('workflow_transitions')->where(fn ($query) => $query
                    ->where('workflow_id', $workflow->id)
                    ->where('from_level_id', $request->input('from_level_id')))->ignore($transition),
            ],
            'label' => ['required', 'string', 'max:120'],
            'direction' => ['required', Rule::in(['forward', 'backward', 'stay'])],
            'status' => ['required', 'string', 'max:64'],
            'complete' => ['sometimes', 'boolean'],
            'form_schema' => ['nullable', 'json'],
        ]);
        $data['workflow_id'] = $workflow->id;
        $data['meta'] = ['complete' => (bool) ($data['complete'] ?? false)];
        $data['form_schema'] = $data['form_schema']
            ? json_decode($data['form_schema'], true, flags: JSON_THROW_ON_ERROR)
            : null;
        unset($data['complete']);

        return $data;
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
