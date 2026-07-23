<div class="qnox-workflows">
    @include('workflows::settings._shell')
    <h1>Workflow Permissions</h1>
    <p><strong>{{ data_get($user, 'name') ?? data_get($user, 'full_name') ?? 'User #'.$user->getAuthIdentifier() }}</strong> {{ data_get($user, 'email') }}</p>
    <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'participants.user.update', $user->getAuthIdentifier()) }}">
        @csrf @method('PUT')
        @foreach($workflows as $workflow)
            <div class="card">
                <h3>{{ $workflow->name }}</h3>
                <p class="muted">{{ $workflow->group?->name }} @if($workflow->module) / {{ $workflow->module->name }} @endif</p>
                @foreach($workflow->levels as $level)
                    <label style="display:block;margin:.45rem 0">
                        <input style="width:auto" type="checkbox" name="level_ids[]" value="{{ $level->id }}" @checked(in_array($level->id, $selected, true))>
                        {{ $level->sequence }}. {{ $level->name }} — {{ ucfirst($level->assignment_mode) }}
                    </label>
                @endforeach
            </div>
        @endforeach
        <button>Save workflow permissions</button>
    </form>
</div>
