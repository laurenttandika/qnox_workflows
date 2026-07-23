<div class="qnox-workflows">
    @include('workflows::settings._shell')
    <h1>User Workflow Permissions</h1>
    <p class="muted">Choose a user to configure the workflow levels in which they may participate.</p>
    <div class="card"><table><thead><tr><th>User</th><th>Email</th><th>Configuration</th></tr></thead><tbody>
    @forelse($users as $user)
        <tr>
            <td>{{ data_get($user, 'name') ?? data_get($user, 'full_name') ?? 'User #'.$user->getAuthIdentifier() }}</td>
            <td>{{ data_get($user, 'email') }}</td>
            <td><a href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'participants.user', $user->getAuthIdentifier()) }}">Workflow Permissions</a></td>
        </tr>
    @empty <tr><td colspan="3">No users found.</td></tr> @endforelse
    </tbody></table></div>
</div>
