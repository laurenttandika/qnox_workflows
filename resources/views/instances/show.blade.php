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
        @include(config('workflows.views.actions'), compact('instance', 'actions'))
        @if(empty($actions))<p class="muted">There are no actions available to you at this level.</p>@endif
    </div>
    <div class="card">
        <h2>History</h2>
        <table><thead><tr><th>Level</th><th>Status</th><th>Entered</th><th>Exited</th><th>Comments</th></tr></thead><tbody>
        @foreach($instance->history as $entry)
            <tr><td>{{ $entry->level?->name }}</td><td>{{ $entry->status }}</td><td>{{ $entry->entered_at }}</td><td>{{ $entry->exited_at }}</td><td>{{ $entry->comments }}</td></tr>
        @endforeach
        </tbody></table>
    </div>
</div>
