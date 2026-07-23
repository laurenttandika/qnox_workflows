<div class="qnox-workflows">
    @include('workflows::settings._shell')
    <h1>Workflow Settings</h1>
    <p class="muted">Configure reusable business modules, approval definitions, assignments, actions, and document number formats.</p>
    <div class="grid">
        @foreach($counts as $label => $count)
            <div class="card"><strong style="font-size:1.8rem">{{ $count }}</strong><br>{{ ucwords(str_replace('_', ' ', $label)) }}</div>
        @endforeach
    </div>
</div>
