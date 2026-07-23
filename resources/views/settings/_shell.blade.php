<style>
    .qnox-workflows{max-width:1100px;margin:2rem auto;font:14px/1.5 system-ui,sans-serif;color:#172033}
    .qnox-workflows nav{display:flex;gap:1rem;margin-bottom:1.5rem}.qnox-workflows a{color:#2457c5}
    .qnox-workflows .grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem}
    .qnox-workflows .card{border:1px solid #dce2ec;border-radius:8px;padding:1rem;background:#fff;margin-bottom:1rem}
    .qnox-workflows table{width:100%;border-collapse:collapse}.qnox-workflows th,.qnox-workflows td{padding:.65rem;border-bottom:1px solid #e7ebf1;text-align:left}
    .qnox-workflows input,.qnox-workflows select,.qnox-workflows textarea{width:100%;box-sizing:border-box;padding:.55rem;border:1px solid #bdc6d5;border-radius:5px}
    .qnox-workflows button{padding:.55rem .85rem;border:0;border-radius:5px;background:#2457c5;color:#fff;cursor:pointer}
    .qnox-workflows .fields{display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:.75rem;align-items:end}
    .qnox-workflows .status{padding:.75rem;background:#e7f7ed;color:#176735;border-radius:5px;margin-bottom:1rem}
    .qnox-workflows .muted{color:#667085}.qnox-workflows dialog{border:0;border-radius:8px;box-shadow:0 15px 50px #0004;max-width:520px;width:90%}
</style>
<nav>
    <a href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'dashboard') }}">Overview</a>
    <a href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'groups.index') }}">Module Groups</a>
    <a href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'modules.index') }}">Modules</a>
    <a href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'definitions.index') }}">Definitions</a>
    <a href="{{ route(config('workflows.routes.web.name_prefix', 'workflows.').'numbers.index') }}">Number Formats</a>
</nav>
@if(session('workflow_status')) <div class="status">{{ session('workflow_status') }}</div> @endif
@if($errors->any())
    <div class="card" style="color:#a12020"><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>
@endif
