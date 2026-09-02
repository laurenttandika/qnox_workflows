<div class="qnox-workflows">
    @include('workflows::settings._shell')
    <h1>{{ $instance->workflow->name }}</h1>
    <div class="card">
        <strong>Status:</strong> {{ $instance->status }}<br>
        <strong>Current level:</strong> {{ $instance->currentLevel?->name }}<br>
        <strong>Subject:</strong> {{ $instance->subject_type }} #{{ $instance->subject_id }}
    </div>
    <div class="card">
        <h2>Available actions</h2>
        @foreach($actions as $action)<form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'instances.decide', $instance) }}" class="card">@csrf<input type="hidden" name="action" value="{{ $action['action'] }}"><label for="comment-{{ $action['action'] }}">Comment</label><textarea id="comment-{{ $action['action'] }}" name="comment"></textarea><button>{{ $action['label'] }}</button></form>@endforeach
        @if(empty($actions))<p class="muted">There are no actions available to you at this level.</p>@endif
    </div>
    <div class="card">
        <h2>History</h2>
        <table><thead><tr><th>Level</th><th>Status</th><th>Entered</th><th>Exited</th></tr></thead><tbody>
        @foreach($instance->history as $entry)
            <tr><td>{{ $entry->level_name }}</td><td>{{ $entry->status }}</td><td>{{ $entry->entered_at }}</td><td>{{ $entry->exited_at }}</td></tr>
        @endforeach
        </tbody></table>
    </div>
</div>
