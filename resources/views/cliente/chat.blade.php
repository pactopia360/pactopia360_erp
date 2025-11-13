{{-- resources/views/cliente/chat.blade.php (v4 visual Pactopia360) --}}
@extends('layouts.client')
@section('title','Soporte · Chat')

@push('styles')
<style>
/* ============================================================
   PACTOPIA360 · Chat Soporte (visual 4.0 unificado)
   ============================================================ */
.chat-page{
  font-family:'Poppins',system-ui,sans-serif;
  --rose:#E11D48;--rose-dark:#BE123C;
  --mut:#6b7280;--card:#fff;--border:#f3d5dc;--chip:#fff8f9;--bg:#fff8f9;
  color:#0f172a;display:grid;gap:18px;padding:20px;
}
html[data-theme="dark"] .chat-page{
  --card:#0b1220;--border:#2b2f36;--chip:#111827;--bg:#0e172a;--mut:#a5adbb;color:#e5e7eb;
}
.page-header{
  background:linear-gradient(90deg,#E11D48,#BE123C);
  color:#fff;padding:18px 22px;border-radius:16px;
  box-shadow:0 8px 22px rgba(225,29,72,.25);
  display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px;
}
.page-title{margin:0;font-weight:900;font-size:22px;}
.page-header .muted{color:rgba(255,255,255,.85);font-size:13px;}
.card{
  background:var(--card);border:1px solid var(--border);border-radius:16px;
  box-shadow:0 6px 22px rgba(225,29,72,.06);padding:20px;display:flex;flex-direction:column;gap:14px;
}
.chat-wrap{
  display:flex;flex-direction:column;gap:10px;max-height:60vh;overflow:auto;
  padding-right:4px;scrollbar-width:thin;
}
.msg{
  display:inline-block;max-width:80%;padding:10px 14px;border-radius:14px;
  word-wrap:break-word;box-shadow:0 2px 10px rgba(0,0,0,.05);animation:fadeIn .2s ease;
}
.msg.client{
  align-self:flex-end;background:linear-gradient(90deg,#E11D48,#BE123C);
  color:#fff;border:0  ;
}
.msg.support{
  align-self:flex-start;background:var(--chip);border:1px solid var(--border);
  color:var(--mut);
}
.meta{
  margin-top:4px;font-size:11.5px;opacity:.85;display:flex;gap:6px;
  justify-content:flex-end;
}
.msg.support .meta{justify-content:flex-start;color:var(--mut);}
.composer{
  display:flex;gap:8px;margin-top:10px;
}
.composer textarea{
  flex:1;min-height:70px;border-radius:12px;border:1px solid var(--border);
  background:var(--chip);color:var(--text,#0f172a);
  padding:10px;font:inherit;resize:vertical;font-weight:600;
}
.btn{
  display:inline-flex;align-items:center;justify-content:center;
  gap:8px;padding:10px 18px;border-radius:12px;font-weight:800;
  background:linear-gradient(90deg,#E11D48,#BE123C);color:#fff;
  border:0;cursor:pointer;transition:.2s all;
  box-shadow:0 4px 16px rgba(225,29,72,.25);
}
.btn:hover{filter:brightness(.96);}
.muted{color:var(--mut);}
@keyframes fadeIn{from{opacity:0;transform:translateY(6px);}to{opacity:1;transform:none;}}
</style>
@endpush

@section('content')
<div class="chat-page">
  <div class="page-header">
    <div>
      <h1 class="page-title">Chat con soporte</h1>
      <div class="muted">Estamos aquí para ayudarte.</div>
    </div>
  </div>

  <div class="card">
    @if(!$has_table)
      <p class="muted">
        💬 El chat aún no está habilitado para tu cuenta.  
        Podemos activarlo cuando exista la tabla 
        <code>mysql_clientes.soporte_mensajes</code>.
      </p>
    @endif

    <div class="chat-wrap" id="chatList">
      @forelse(($items ?? []) as $m)
        @php
          $role = strtolower((string)($m->role ?? 'cliente'));
          $body = (string)($m->body ?? '');
          try { $ts = \Carbon\Carbon::parse($m->created_at)->format('Y-m-d H:i'); }
          catch (\Throwable $e) { $ts = (string)($m->created_at ?? ''); }
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
</div>
@endsection

@push('scripts')
<script>
async function sendMsg(ev){
  ev.preventDefault();
  const ta=document.getElementById('chatBody');
  const list=document.getElementById('chatList');
  const body=(ta.value||'').trim();
  if(!body)return false;
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
  try{
    const res=await fetch(@json(route('cliente.soporte.chat.send')),{
      method:'POST',
      headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},
      body:JSON.stringify({body})
    });
    const data=await res.json();
    if(!res.ok||!data?.ok)throw new Error(data?.msg||'Error');
    const d=document.createElement('div');
    d.className='msg client';
    d.innerHTML=`<div>${body.replace(/\n/g,'<br>')}</div><div class="meta"><span>Tú</span><span>·</span><span>ahora</span></div>`;
    list.appendChild(d);
    ta.value='';ta.focus();
    list.scrollTop=list.scrollHeight;
  }catch(e){alert('No se pudo enviar: '+(e?.message||'Error'));}
  return false;
}
</script>
@endpush
