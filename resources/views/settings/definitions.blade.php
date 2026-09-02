<div class="qnox-workflows">@include('workflows::settings._shell')
<div class="row"><div><h1>{{ $moduleLabel }} approval workflows</h1><p class="muted">{{ $moduleKey }}</p></div><a class="button" href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.create', ['module' => $moduleKey]) }}">Create workflow</a></div>
<div class="card"><table><thead><tr><th>Name</th><th>Status</th><th>Levels</th></tr></thead><tbody>
@forelse($workflows as $workflow)<tr><td><a href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.edit', $workflow) }}">{{ $workflow->name }}</a></td><td>{{ $workflow->is_active ? 'Active' : 'Inactive' }}</td><td>{{ $workflow->levels_count }}</td></tr>
@empty<tr><td colspan="3">No workflows configured.</td></tr>@endforelse
</tbody></table></div></div>
