{{-- resources/views/cliente/chat.blade.php --}}
@extends('layouts.client')
@section('title','Soporte · Chat')

@push('styles')
<style>
  .page-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:14px }
  .page-title{ margin:0; font-size:clamp(18px,2.4vw,22px); font-weight:900; color:var(--text) }
  .muted{ color:var(--muted) }
  .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:14px; box-shadow:var(--shadow) }
  .chat-wrap{ display:flex; flex-direction:column; gap:10px; max-height:60vh; overflow:auto }
  .msg{ display:grid; gap:6px; padding:10px; border:1px solid var(--border); border-radius:12px; }
  .msg.client{ background:color-mix(in oklab, var(--blue) 10%, transparent); justify-self:flex-end }
  .msg.support{ background:var(--chip) }
  .meta{ color:var(--muted); font-size:12px; display:flex; gap:10px }
  .composer{ display:flex; gap:8px; margin-top:12px }
  .composer textarea{ flex:1; min-height:60px; border-radius:12px; border:1px solid var(--border); background:var(--chip); color:var(--text); padding:10px; font:inherit; resize:vertical }
  .btn{ display:inline-flex; align-items:center; gap:8px; padding:10px 12px; border-radius:12px; border:1px solid var(--border); background:linear-gradient(180deg, var(--accent), color-mix(in oklab, var(--accent) 85%, black)); color:#fff; font-weight:900 }
</style>
@endpush

@section('content')
<div class="page-header">
  <div>
    <h1 class="page-title">Chat con soporte</h1>
    <div class="muted">Escríbenos tu duda y te respondemos aquí.</div>
  </div>
</div>

<div class="card">
  @if(!$has_table)
    <p class="muted">Aún no está habilitado el chat en tu cuenta. Podemos activarlo cuando esté lista la tabla <code>mysql_clientes.soporte_mensajes</code>.</p>
  @endif

  <div class="chat-wrap" id="chatList">
    @forelse(($items ?? []) as $m)
      @php
        $role = strtolower((string)($m->role ?? 'cliente'));
        $body = (string)($m->body ?? '');
        try { $ts = \Carbon\Carbon::parse($m->created_at)->format('Y-m-d H:i'); } catch (\Throwable $e) { $ts = (string)($m->created_at ?? ''); }
      @endphp
      <div class="msg {{ $role === 'cliente' ? 'client' : 'support' }}">
        <div>{!! nl2br(e($body)) !!}</div>
        <div class="meta">
          <span>{{ $role === 'cliente' ? 'Tú' : 'Soporte' }}</span>
          <span>·</span>
          <span>{{ $ts }}</span>
        </div>
      </div>
    @empty
      <p class="muted">Aún no hay mensajes.</p>
    @endforelse
  </div>

  <form class="composer" id="chatForm" onsubmit="return sendMsg(event)">
    @csrf
    <textarea name="body" id="chatBody" placeholder="Escribe tu mensaje..."></textarea>
    <button class="btn" type="submit">Enviar</button>
  </form>
</div>
@endsection

@push('scripts')
<script>
async function sendMsg(ev){
  ev.preventDefault();
  const ta   = document.getElementById('chatBody');
  const list = document.getElementById('chatList');
  const body = (ta.value || '').trim();
  if (!body) return false;

  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  try{
    const res = await fetch(@json(route('cliente.soporte.chat.send')), {
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
      body: JSON.stringify({ body })
    });
    const data = await res.json();
    if(!res.ok || !data?.ok){ throw new Error(data?.msg || 'Error'); }

    // Pinta optimista
    const d = document.createElement('div');
    d.className = 'msg client';
    d.innerHTML = `<div>${body.replace(/\n/g,'<br>')}</div><div class="meta"><span>Tú</span><span>·</span><span>ahora</span></div>`;
    list.prepend(d);
    ta.value = '';
    ta.focus();
  }catch(e){
    alert('No se pudo enviar: ' + (e?.message || 'Error'));
  }
  return false;
}
</script>
@endpush
