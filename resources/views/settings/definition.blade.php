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
            <label>Description<input name="description"></label>
            <label><input style="width:auto" type="checkbox" name="is_start" value="1"> Start level</label>
            <label><input style="width:auto" type="checkbox" name="is_terminal" value="1"> Terminal level</label>
            <label><input style="width:auto" type="checkbox" name="is_approval" value="1" checked> Approval level</label>
            <button>Add level</button>
        </div>
    </form>
    <div class="card"><table><thead><tr><th>#</th><th>Name</th><th>Start</th><th>Terminal</th></tr></thead><tbody>
    @foreach($workflow->levels as $level)<tr><td>{{ $level->sequence }}</td><td>{{ $level->name }}</td><td>{{ $level->is_start ? 'Yes' : '—' }}</td><td>{{ $level->is_terminal ? 'Yes' : '—' }}</td></tr>@endforeach
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
            <label><input style="width:auto" type="checkbox" name="complete" value="1"> Completes workflow</label>
            <button>Add transition</button>
        </div>
    </form>
    <div class="card"><table><thead><tr><th>From</th><th>Action</th><th>To</th><th>Status</th></tr></thead><tbody>
    @foreach($workflow->transitions as $transition)<tr><td>{{ $transition->fromLevel?->name }}</td><td>{{ $transition->label }} ({{ $transition->action_key }})</td><td>{{ $transition->toLevel?->name ?? '—' }}</td><td>{{ $transition->status }}</td></tr>@endforeach
    </tbody></table></div>
</div>
