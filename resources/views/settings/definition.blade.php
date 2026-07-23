<div class="qnox-workflows">
    @include('workflows::settings._shell')
    <h1>{{ $workflow->name }}</h1>
    <p class="muted">{{ $workflow->group?->name }} @if($workflow->module) / {{ $workflow->module->name }} @endif</p>

    <h2>Levels</h2>
    <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.levels.store', $workflow) }}" class="card">
        @csrf
        <div class="fields">
            <label>Name<input name="name" required></label>
            <label>Sequence<input name="sequence" type="number" min="1" required></label>
            <label>Assignment mode<select name="assignment_mode"><option value="pooled">Pooled / Attend</option><option value="automatic">Automatic</option><option value="direct">Direct selection</option></select></label>
            <label>Description<input name="description"></label>
            <label><input style="width:auto" type="checkbox" name="is_start" value="1"> Start level</label>
            <label><input style="width:auto" type="checkbox" name="is_terminal" value="1"> Terminal level</label>
            <label><input style="width:auto" type="checkbox" name="is_approval" value="1" checked> Approval level</label>
            <label><input style="width:auto" type="checkbox" name="allow_rejection" value="1" checked> Allow rejection</label>
            <label><input style="width:auto" type="checkbox" name="can_close" value="1" checked> Can close</label>
            <label><input style="width:auto" type="checkbox" name="is_optional" value="1"> Optional</label>
            <input type="hidden" name="rules" value="">
            <button>Add level</button>
        </div>
    </form>
    <div class="card"><table><thead><tr><th>#</th><th>Name</th><th>Assignment</th><th>Start</th><th>Terminal</th><th>Configure</th></tr></thead><tbody>
    @foreach($workflow->levels as $level)
        <tr>
            <td>{{ $level->sequence }}</td><td>{{ $level->name }}</td><td>{{ ucfirst($level->assignment_mode) }}</td><td>{{ $level->is_start ? 'Yes' : '—' }}</td><td>{{ $level->is_terminal ? 'Yes' : '—' }}</td>
            <td><details><summary>Edit</summary>
                <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.levels.update', [$workflow, $level]) }}" style="min-width:320px">
                    @csrf @method('PUT')
                    <label>Name<input name="name" value="{{ $level->name }}" required></label>
                    <label>Sequence<input type="number" name="sequence" value="{{ $level->sequence }}" required></label>
                    <label>Assignment mode<select name="assignment_mode">@foreach(['pooled','automatic','direct'] as $mode)<option value="{{ $mode }}" @selected($level->assignment_mode === $mode)>{{ ucfirst($mode) }}</option>@endforeach</select></label>
                    <label>Description<textarea name="description">{{ $level->description }}</textarea></label>
                    @foreach(['is_start'=>'Start','is_terminal'=>'Terminal','is_approval'=>'Approval','is_optional'=>'Optional','allow_rejection'=>'Allow rejection','allow_repeat_participate'=>'Repeat participation','allow_round_robin'=>'Round robin','can_close'=>'Can close'] as $field => $label)
                        <label><input style="width:auto" type="checkbox" name="{{ $field }}" value="1" @checked($level->{$field})> {{ $label }}</label>
                    @endforeach
                    <label>Next message<input name="msg_next" value="{{ $level->msg_next }}"></label>
                    <label>Action description<input name="action_description" value="{{ $level->action_description }}"></label>
                    <label>Status description<input name="status_description" value="{{ $level->status_description }}"></label>
                    <label>Rules JSON<textarea name="rules">{{ $level->rules ? json_encode($level->rules) : '' }}</textarea></label>
                    <button>Save level</button>
                </form>
            </details></td>
        </tr>
    @endforeach
    </tbody></table></div>

    <h2>Definition participants</h2>
    <p class="muted">These permissions control who may see, attend, and act at each level. Routing assignments are intersected with this list.</p>
    <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.participants.store', $workflow) }}" class="card">
        @csrf
        <div class="fields">
            <label>Level<select name="workflow_level_id" required>@foreach($workflow->levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select></label>
            <label>Participant type<select name="type">@foreach(config('workflows.participant_options', []) as $type => $option)<option value="{{ $type }}">{{ $option['label'] ?? ucfirst($type) }}</option>@endforeach</select></label>
            <label>Participant model ID<input name="participant_id" type="number" min="1" required></label>
            <label>Role<input name="role" placeholder="approver"></label>
            <label><input style="width:auto" type="checkbox" name="can_view" value="1" checked> Can view</label>
            <label><input style="width:auto" type="checkbox" name="can_claim" value="1" checked> Can attend</label>
            <label><input style="width:auto" type="checkbox" name="can_act" value="1" checked> Can act</label>
            <button>Save participant</button>
        </div>
    </form>
    <div class="card"><table><thead><tr><th>Level</th><th>Type</th><th>Participant</th><th>Role</th><th>Permissions</th></tr></thead><tbody>
    @forelse($workflow->levels->flatMap->participants as $participant)
        <tr>
            <td>{{ $workflow->levels->firstWhere('id', $participant->workflow_level_id)?->name }}</td>
            <td>{{ $participant->type }}</td>
            <td>{{ data_get($participant->participant, 'name') ?? data_get($participant->participant, 'email') ?? class_basename($participant->participant_type).' #'.$participant->participant_id }}</td>
            <td>{{ $participant->role }}</td>
            <td>{{ $participant->can_view ? 'View ' : '' }}{{ $participant->can_claim ? 'Attend ' : '' }}{{ $participant->can_act ? 'Act' : '' }}</td>
        </tr>
    @empty <tr><td colspan="5">No explicit participants. Existing level assignments are used as the compatibility fallback.</td></tr> @endforelse
    </tbody></table></div>

    <h2>Assignments</h2>
    <p class="muted">Use an application model ID, or criteria such as {"initiator":true} or {"user_ids":[1,2]}.</p>
    <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.assignments.store', $workflow) }}" class="card">
        @csrf
        <div class="fields">
            <label>Level<select name="workflow_level_id" required>@foreach($workflow->levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select></label>
            <label>Assignment type<select name="type" required>@foreach(config('workflows.assignment_options', []) as $type => $option)<option value="{{ $type }}">{{ $option['label'] ?? ucfirst($type) }}</option>@endforeach</select></label>
            <label>Model ID <span class="muted">(optional)</span><input name="assignable_id" type="number" min="1"></label>
            <label>Criteria JSON <span class="muted">(optional)</span><input name="criteria" placeholder='{"initiator":true}'></label>
            <button>Add assignment</button>
        </div>
    </form>
    <div class="card"><table><thead><tr><th>Level</th><th>Type</th><th>Model</th><th>ID</th><th>Criteria</th></tr></thead><tbody>
    @forelse($workflow->levels->flatMap->assignments as $assignment)
        <tr><td>{{ $assignment->level?->name ?? $workflow->levels->firstWhere('id', $assignment->workflow_level_id)?->name }}</td><td>{{ $assignment->type }}</td><td>{{ $assignment->assignable_type }}</td><td>{{ $assignment->assignable_id }}</td><td>{{ json_encode($assignment->criteria) }}</td></tr>
    @empty <tr><td colspan="5">No assignments configured.</td></tr> @endforelse
    </tbody></table></div>

    <h2>Actions and transitions</h2>
    <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.transitions.store', $workflow) }}" class="card">
        @csrf
        <div class="fields">
            <label>From<select name="from_level_id" required>@foreach($workflow->levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select></label>
            <label>To<select name="to_level_id"><option value="">Same/terminal</option>@foreach($workflow->levels as $level)<option value="{{ $level->id }}">{{ $level->name }}</option>@endforeach</select></label>
            <label>Action key<input name="action_key" placeholder="approve" required></label>
            <label>Button label<input name="label" placeholder="Approve" required></label>
            <label>Direction<select name="direction"><option>forward</option><option>backward</option><option>stay</option></select></label>
            <label>Status<input name="status" value="in_progress" required></label>
            <label>Action form JSON <span class="muted">(optional)</span><textarea name="form_schema" placeholder='{"fields":[{"name":"next_user_id","type":"number","label":"Next user ID","required":true}]}'></textarea></label>
            <label><input style="width:auto" type="checkbox" name="complete" value="1"> Completes workflow</label>
            <button>Add transition</button>
        </div>
    </form>
    <div class="card"><table><thead><tr><th>From</th><th>Action</th><th>To</th><th>Status</th></tr></thead><tbody>
    @foreach($workflow->transitions as $transition)<tr><td>{{ $transition->fromLevel?->name }}</td><td>{{ $transition->label }} ({{ $transition->action_key }})</td><td>{{ $transition->toLevel?->name ?? '—' }}</td><td>{{ $transition->status }}</td></tr>@endforeach
    </tbody></table></div>
</div>
