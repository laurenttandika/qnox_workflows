<div class="qnox-workflows">
    @include('workflows::settings._shell')
    <h1>Workflow Module Groups</h1>
    <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'groups.store') }}" class="card">
        @csrf
        <div class="fields">
            <label>Name<input name="name" required value="{{ old('name') }}"></label>
            <label>Slug <span class="muted">(optional)</span><input name="slug" value="{{ old('slug') }}"></label>
            <button>Create group</button>
        </div>
    </form>
    <div class="card">
        <table><thead><tr><th>Name</th><th>Slug</th><th>Modules</th><th>Definitions</th><th>Configure</th></tr></thead><tbody>
        @forelse($groups as $group)
            <tr><td>{{ $group->name }}</td><td>{{ $group->slug }}</td><td>{{ $group->modules_count }}</td><td>{{ $group->workflows_count }}</td><td><details><summary>Edit</summary>
                <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'groups.update', $group) }}">
                    @csrf @method('PUT')
                    <label>Name<input name="name" value="{{ $group->name }}" required></label>
                    <label>Slug<input name="slug" value="{{ $group->slug }}" required></label>
                    <button>Save group</button>
                </form>
            </details></td></tr>
        @empty <tr><td colspan="5">No module groups configured.</td></tr> @endforelse
        </tbody></table>
    </div>
</div>
