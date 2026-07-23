<div class="qnox-inbox">
    <style>
        .qnox-inbox{max-width:1100px;margin:2rem auto;font:14px/1.5 system-ui,sans-serif;color:#172033}
        .qnox-inbox nav{display:flex;gap:.5rem;flex-wrap:wrap;margin-bottom:1.5rem}
        .qnox-inbox nav a{padding:.55rem .8rem;border:1px solid #dce2ec;border-radius:6px;text-decoration:none;color:#2457c5}
        .qnox-inbox nav a.active{background:#2457c5;color:#fff}.qnox-inbox .badge{display:inline-block;margin-left:.35rem;padding:.05rem .4rem;border-radius:1rem;background:#e6eaf2;color:#172033}
        .qnox-inbox .card{border:1px solid #dce2ec;border-radius:8px;padding:1rem;background:#fff}
        .qnox-inbox table{width:100%;border-collapse:collapse}.qnox-inbox th,.qnox-inbox td{padding:.7rem;border-bottom:1px solid #e7ebf1;text-align:left}
        .qnox-inbox a{color:#2457c5}.qnox-inbox .muted{color:#667085}
    </style>
    <h1>Workflow Inbox</h1>
    <nav>
        @foreach($categories as $name)
            <a class="{{ $category === $name ? 'active' : '' }}" href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'inbox.'.$name) }}">
                {{ ucfirst($name) }} <span class="badge">{{ $counts[$name] ?? 0 }}</span>
            </a>
        @endforeach
    </nav>
    <div class="card">
        <table>
            <thead><tr><th>Workflow</th><th>Subject</th><th>Level</th><th>Status</th><th>Received</th></tr></thead>
            <tbody>
            @forelse($items as $item)
                <tr>
                    <td><a href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'inbox.show', $item->instance) }}">{{ $item->instance->workflow?->name }}</a></td>
                    <td>
                        @if($item->instance->subject)
                            {{ method_exists($item->instance->subject, '__toString') ? (string) $item->instance->subject : class_basename($item->instance->subject_type).' #'.$item->instance->subject_id }}
                        @else
                            {{ class_basename($item->instance->subject_type) }} #{{ $item->instance->subject_id }}
                        @endif
                    </td>
                    <td>{{ $item->instanceLevel?->level?->name }}</td>
                    <td>{{ $item->instance->status }}</td>
                    <td>{{ $item->created_at?->diffForHumans() }}</td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No {{ $category }} workflow items.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
