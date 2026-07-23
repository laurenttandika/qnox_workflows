<div class="qnox-workflows">
    @include('workflows::settings._shell')
    <h1>Workflow Modules</h1>
    <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'modules.store') }}" class="card">
        @csrf
        <div class="fields">
            <label>Group<select name="workflow_group_id" required>@foreach($groups as $group)<option value="{{ $group->id }}">{{ $group->name }}</option>@endforeach</select></label>
            <label>Name<input name="name" required value="{{ old('name') }}"></label>
            <label>Slug <span class="muted">(optional)</span><input name="slug" value="{{ old('slug') }}"></label>
            <label><input style="width:auto" type="checkbox" name="is_active" value="1" checked> Active</label>
            <button>Create module</button>
        </div>
    </form>
    <div class="card">
        <table><thead><tr><th>Name</th><th>Group</th><th>Slug</th><th>Definitions</th><th>Status</th></tr></thead><tbody>
        @forelse($modules as $module)
            <tr><td>{{ $module->name }}</td><td>{{ $module->group?->name }}</td><td>{{ $module->slug }}</td><td>{{ $module->workflows_count }}</td><td>{{ $module->is_active ? 'Active' : 'Inactive' }}</td></tr>
        @empty <tr><td colspan="5">No modules configured.</td></tr> @endforelse
        </tbody></table>
    </div>
</div>
