<div class="qnox-workflows">
    @include('workflows::settings._shell')
    <h1>Number Formats</h1>
    <p class="muted">Tokens: {prefix}, {number}, {year}, {year:2}, {month}, {day}, {module}, {department}, {unit}, {tenant}, {subject_id}</p>
    <form method="post" action="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'numbers.store') }}" class="card">
        @csrf
        <div class="fields">
            <label>Name<input name="name" required value="{{ old('name') }}"></label>
            <label>Key<input name="key" required placeholder="payment-requisition" value="{{ old('key') }}"></label>
            <label>Prefix<input name="prefix" placeholder="QNOX/PR" value="{{ old('prefix') }}"></label>
            <label>Format<input name="format" required value="{{ old('format', '{prefix}/{year}/{number}') }}"></label>
            <label>Next value<input type="number" min="1" name="next_value" value="{{ old('next_value', 1) }}"></label>
            <label>Padding<input type="number" min="1" max="20" name="padding" value="{{ old('padding', config('workflows.numbering.default_padding', 6)) }}"></label>
            <label>Reset<select name="reset_period">@foreach(['never','yearly','monthly','daily'] as $period)<option>{{ $period }}</option>@endforeach</select></label>
            <label><input style="width:auto" type="checkbox" name="is_active" value="1" checked> Active</label>
            <button>Create format</button>
        </div>
    </form>
    <div class="card">
        <table><thead><tr><th>Name</th><th>Key</th><th>Format</th><th>Next</th><th>Reset</th><th>Status</th></tr></thead><tbody>
        @forelse($sequences as $sequence)
            <tr><td>{{ $sequence->name }}</td><td>{{ $sequence->key }}</td><td>{{ $sequence->format }}</td><td>{{ $sequence->next_value }}</td><td>{{ $sequence->reset_period }}</td><td>{{ $sequence->is_active ? 'Active' : 'Inactive' }}</td></tr>
        @empty <tr><td colspan="6">No number formats configured.</td></tr> @endforelse
        </tbody></table>
    </div>
</div>
