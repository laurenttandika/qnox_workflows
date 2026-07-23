<div class="workflow-actions">
    @foreach($actions as $action)
        @php($modalId = 'workflow-action-'.$instance->id.'-'.$action['action_key'])
        <button type="button" onclick="document.getElementById('{{ $modalId }}').showModal()">{{ $action['label'] }}</button>
        @include(config('workflows.views.action_modal'), compact('instance', 'action', 'modalId'))
    @endforeach
</div>
