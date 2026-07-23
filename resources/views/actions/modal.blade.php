<dialog id="{{ $modalId }}">
    <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'instances.act', $instance) }}">
        @csrf
        <input type="hidden" name="action_key" value="{{ $action['action_key'] }}">
        <h3>{{ $action['label'] }}</h3>
        @if(data_get($action, 'form_schema.confirmation'))<p>{{ data_get($action, 'form_schema.confirmation') }}</p>@endif
        @foreach((array) data_get($action, 'form_schema.fields', [['name' => 'comment', 'type' => 'textarea', 'label' => 'Comments']]) as $field)
            <label>{{ $field['label'] ?? ucfirst(str_replace('_', ' ', $field['name'])) }}
                @if(($field['type'] ?? 'text') === 'textarea')
                    <textarea name="payload[{{ $field['name'] }}]" @required($field['required'] ?? false)></textarea>
                @else
                    <input type="{{ $field['type'] ?? 'text' }}" name="payload[{{ $field['name'] }}]" @required($field['required'] ?? false)>
                @endif
            </label>
        @endforeach
        <div style="display:flex;gap:.5rem;margin-top:1rem">
            <button type="submit">{{ $action['label'] }}</button>
            <button type="button" onclick="document.getElementById('{{ $modalId }}').close()">Cancel</button>
        </div>
    </form>
</dialog>
