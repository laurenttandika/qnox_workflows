<div class="qnox-workflows">
    @include('workflows::settings._shell')
    <h1>Workflow Definitions</h1>
    <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.store') }}" class="card">
        @csrf
        <div class="fields">
            <label>Group<select name="workflow_group_id" required>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select></label>
            <label>Module<select name="workflow_module_id"><option value="">None</option>@foreach($modules as $module)<option value="{{ $module->id }}">{{ $module->name }}</option>@endforeach</select></label>
            <label>Name<input name="name" required></label>
            <label>Slug <span class="muted">(optional)</span><input name="slug"></label>
            <label><input style="width:auto" type="checkbox" name="is_active" value="1" checked> Active</label>
            <button>Create definition</button>
        </div>
    </form>
    <div class="card"><table><thead><tr><th>Name</th><th>Group</th><th>Module</th><th>Levels</th><th>Status</th></tr></thead><tbody>
    @forelse($workflows as $workflow)
        <tr>
            <td><a href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.show', $workflow) }}">{{ $workflow->name }}</a></td>
            <td>{{ $workflow->group?->name }}</td><td>{{ $workflow->module?->name ?? '—' }}</td>
            <td>{{ $workflow->levels_count }}</td><td>{{ $workflow->is_active ? 'Active' : 'Inactive' }}</td>
        </tr>
    @empty <tr><td colspan="5">No workflow definitions configured.</td></tr> @endforelse
    </tbody></table></div>
</div>
