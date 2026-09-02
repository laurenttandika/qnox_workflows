<div class="qnox-workflows">@include('workflows::settings._shell')
<h1>Registered workflow modules</h1><p class="muted">Modules are registered by the application and cannot be edited here.</p>
<div class="card"><table><thead><tr><th>Module</th><th></th></tr></thead><tbody>
@forelse($modules as $key => $label)<tr><td>{{ $label }}<br><small class="muted">{{ $key }}</small></td><td><a href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.index', ['module' => $key]) }}">Manage workflows</a></td></tr>
@empty<tr><td colspan="2">No modules registered. Add them to <code>config/workflows.php</code>.</td></tr>@endforelse
</tbody></table></div></div>
