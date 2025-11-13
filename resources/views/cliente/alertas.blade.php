{{-- resources/views/cliente/alertas.blade.php (v4 visual Pactopia360) --}}
@extends('layouts.client')
@section('title','Notificaciones · Pactopia360')

@push('styles')
<style>
/* ===========================================================
   PACTOPIA360 · Alertas v4 (visual unificado)
   =========================================================== */
.alertas-wrap{
  font-family:'Poppins',system-ui,sans-serif;
  --rose:#E11D48; --rose-dark:#BE123C;
  --mut:#6b7280; --card:#fff; --border:#f3d5dc; --chip:#fff8f9; --bg:#fff8f9;
  color:#0f172a;
  display:grid;gap:18px;padding:20px;
}
html[data-theme="dark"] .alertas-wrap{
  --card:#0b1220;--border:#2b2f36;--chip:#111827;--bg:#0e172a;--mut:#a5adbb;color:#e5e7eb;
}

.page-header{
  background:linear-gradient(90deg,#E11D48,#BE123C);
  color:#fff;padding:18px 22px;border-radius:16px;
  box-shadow:0 8px 22px rgba(225,29,72,.25);
  display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:14px;
}
.page-title{margin:0;font-weight:900;font-size:22px;}
.page-header .muted{color:rgba(255,255,255,.8);font-size:13px;font-weight:600;}
.toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
.segmented{display:inline-flex;border:1px solid rgba(255,255,255,.3);border-radius:999px;overflow:hidden;}
.segmented a{
  padding:8px 14px;font-weight:800;text-decoration:none;color:#fff;
  background:transparent;transition:.15s ease all;
}
.segmented a.active{background:rgba(255,255,255,.2);}
.counter-badge{
  background:rgba(255,255,255,.15);border:1px solid rgba(255,255,255,.3);
  border-radius:999px;padding:6px 12px;font-weight:800;color:#fff;font-size:13px;
}

.select select{
  all:unset;background:rgba(255,255,255,.15);
  border-radius:10px;padding:8px 12px;color:#fff;font-weight:800;
}
.card{
  background:var(--card);border:1px solid var(--border);border-radius:16px;
  box-shadow:0 6px 22px rgba(225,29,72,.06);padding:14px 18px;
}
.alert-item{
  display:grid;grid-template-columns:32px 1fr auto;gap:12px;align-items:flex-start;
}
.alert-ico{
  width:28px;height:28px;display:grid;place-items:center;border-radius:8px;
  border:1px solid var(--border);background:var(--chip);font-size:16px;
}
.alert-title{margin:0;font-size:15px;font-weight:900;color:var(--rose);}
.alert-desc{margin:4px 0 0;font-size:14px;line-height:1.45;color:var(--mut);}
.meta{display:flex;gap:8px;align-items:center;font-size:12px;color:var(--mut);}
.badge{
  display:inline-flex;align-items:center;gap:6px;padding:4px 8px;border-radius:999px;
  border:1px solid var(--border);font-weight:800;font-size:11.5px;
}
.badge.unread{background:rgba(225,29,72,.12);border-color:rgba(225,29,72,.25);color:var(--rose);}
.badge.type-info{background:rgba(56,189,248,.15);border-color:rgba(56,189,248,.25);}
.badge.type-success{background:rgba(16,185,129,.15);border-color:rgba(16,185,129,.25);}
.badge.type-warning{background:rgba(245,158,11,.15);border-color:rgba(245,158,11,.25);}
.badge.type-error{background:rgba(239,68,68,.15);border-color:rgba(239,68,68,.25);}
.actions .btn{
  display:inline-flex;align-items:center;justify-content:center;width:38px;height:38px;
  border-radius:10px;border:1px solid var(--border);background:var(--chip);
  cursor:pointer;font-size:15px;transition:.15s ease all;
}
.actions .btn:hover{background:color-mix(in oklab,var(--rose)15%,var(--chip));}
.pagination{
  display:flex;gap:8px;justify-content:center;align-items:center;margin-top:18px;
  font-weight:800;font-size:14px;
}
.pagination a,.pagination span{
  padding:6px 10px;border-radius:8px;border:1px solid var(--border);
  background:var(--card);text-decoration:none;color:var(--rose);
}
.pagination span.active{background:var(--rose);color:#fff;}
</style>
@endpush

@section('content')
@php
  $notifs = $notifs ?? null;
  $rows   = $notifs ? $notifs->items() : ($items ?? []);
  $status = $status ?? 'unread';
  $totalCount  = $notifs ? (int) $notifs->total() : (is_countable($rows) ? count($rows) : 0);
  $unreadCount = (int) ($notifCount ?? 0);
  $mkUrl = fn($st) => request()->fullUrlWithQuery(['status'=>$st,'page'=>1]);
  $get = function($n,$keys,$default=null){
    foreach((array)$keys as $k){
      if(is_array($n)&&array_key_exists($k,$n)&&$n[$k]!==null)return $n[$k];
      if(is_object($n)&&isset($n->{$k})&&$n->{$k}!==null)return $n->{$k};
    }
    return $default;
  };
@endphp

<div class="alertas-wrap">
  <div class="page-header">
    <div>
      <h1 class="page-title">Notificaciones</h1>
      <div class="muted">Alertas y eventos importantes del ERP cliente</div>
    </div>

    <div class="toolbar">
      <div class="segmented">
        <a href="{{ $mkUrl('unread') }}" class="{{ $status==='unread'?'active':'' }}">No leídas</a>
        <a href="{{ $mkUrl('read') }}" class="{{ $status==='read'?'active':'' }}">Leídas</a>
        <a href="{{ $mkUrl('all') }}" class="{{ $status==='all'?'active':'' }}">Todas</a>
      </div>
      <form method="GET" action="{{ url()->current() }}" class="toolbar">
        @foreach(request()->except('per_page') as $k => $v)
          <input type="hidden" name="{{ $k }}" value="{{ is_array($v)?json_encode($v):$v }}">
        @endforeach
        <div class="select">
          <select name="per_page" onchange="this.form.submit()">
            @foreach([10,15,20,30,50,100] as $pp)
              <option value="{{ $pp }}" {{ (int)request('per_page',15)===$pp?'selected':'' }}>{{ $pp }}/pág.</option>
            @endforeach
          </select>
        </div>
      </form>
      <span class="counter-badge">Total: <strong id="cntTotal">{{ number_format($totalCount) }}</strong></span>
      <span class="counter-badge">No leídas: <strong id="cntUnread">{{ number_format($unreadCount) }}</strong></span>
    </div>
  </div>

  @if(!$rows || count($rows)===0)
    <div class="card">
      <p class="muted">No tienes notificaciones {{ $status==='unread' ? 'no leídas' : ($status==='read' ? 'leídas' : '') }} por ahora.</p>
    </div>
  @else
    <div class="list" id="alertsList">
      @foreach($rows as $n)
        @php
          $tipo=strtolower((string)($get($n,['tipo','type'],'info')));
          $titulo=trim((string)($get($n,['titulo','title','asunto'],'Notificación')));
          $desc=trim((string)($get($n,['descripcion','description','mensaje','message','body'],'Sin descripción.')));
          $fecha=$get($n,['created_at','fecha','createdAt','ts'],null);
          $id=$get($n,['id','uuid'],null);
          $leidaBool=$get($n,['leida'],null);
          $readAt=$get($n,['read_at'],null);
          $isUnread=is_null($readAt)&&($leidaBool===null||$leidaBool===false);
          $typeClass='type-info';
          if(str_contains($tipo,'warn'))$typeClass='type-warning';
          elseif(str_contains($tipo,'error')||str_contains($tipo,'danger'))$typeClass='type-error';
          elseif(str_contains($tipo,'ok')||str_contains($tipo,'success'))$typeClass='type-success';
          try{$fechaTxt=$fecha?\Carbon\Carbon::parse($fecha)->format('Y-m-d H:i'):'';}catch(\Throwable $e){$fechaTxt=(string)$fecha;}
          $urlRead=$id!==null?route('cliente.alertas.read',$id):null;
          $urlDelete=$id!==null?route('cliente.alertas.delete',$id):null;
        @endphp

        <div class="card alert-item" data-id="{{ $id }}" data-unread="{{ $isUnread?'1':'0' }}">
          <div class="alert-ico">
            @if($typeClass==='type-success')✅
            @elseif($typeClass==='type-warning')⚠️
            @elseif($typeClass==='type-error')❗
            @else ℹ️
            @endif
          </div>

          <div>
            <h4 class="alert-title">
              {{ $titulo }}
              <span class="badge {{ $typeClass }}" style="margin-left:6px;text-transform:uppercase">{{ strtoupper($tipo?:'INFO') }}</span>
              @if($isUnread)
                <span class="badge unread js-unread-badge" style="margin-left:6px">No leída</span>
              @endif
            </h4>
            <p class="alert-desc">{{ $desc }}</p>
            <div class="meta">
              @if($fechaTxt)<span>📅 {{ $fechaTxt }}</span>@endif
              @if($id !== null)<span>#{{ $id }}</span>@endif
            </div>
          </div>

          <div class="actions" style="display:flex;gap:6px;">
            <button class="btn js-mark" title="Marcar como leída" {{ $urlRead?'':'disabled' }} data-url="{{ $urlRead }}">✓</button>
            <button class="btn js-del" title="Eliminar" {{ $urlDelete?'':'disabled' }} data-url="{{ $urlDelete }}">🗑️</button>
          </div>
        </div>
      @endforeach
    </div>

    @if($notifs && $notifs->hasPages())
      <div class="pagination">
        @if($notifs->onFirstPage())
          <span>&laquo;</span>
        @else
          <a href="{{ $notifs->appends(request()->except('page'))->previousPageUrl() }}">&laquo;</a>
        @endif

        @foreach($notifs->getUrlRange(max(1,$notifs->currentPage()-2),min($notifs->lastPage(),$notifs->currentPage()+2)) as $page=>$url)
          @if($page==$notifs->currentPage())
            <span class="active">{{ $page }}</span>
          @else
            <a href="{{ $url }}">{{ $page }}</a>
          @endif
        @endforeach

        @if($notifs->hasMorePages())
          <a href="{{ $notifs->appends(request()->except('page'))->nextPageUrl() }}">&raquo;</a>
        @else
          <span>&raquo;</span>
        @endif
      </div>
    @endif
  @endif
</div>
@endsection

@push('scripts')
<script>
(function(){
  const csrf=document.querySelector('meta[name="csrf-token"]')?.content||'';
  const list=document.getElementById('alertsList');
  const elTotal=document.getElementById('cntTotal');
  const elUnread=document.getElementById('cntUnread');

  function updateCounters(dt,du){
    const toInt=(el)=>parseInt((el?.textContent||'0').replace(/[^\d\-]/g,''),10)||0;
    if(elTotal)elTotal.textContent=(toInt(elTotal)+(dt||0)).toLocaleString();
    if(elUnread)elUnread.textContent=(toInt(elUnread)+(du||0)).toLocaleString();
  }

  if(!list)return;

  list.addEventListener('click',async(ev)=>{
    const btn=ev.target.closest('.js-mark,.js-del');
    if(!btn)return;
    const card=btn.closest('.alert-item');
    const url=btn.dataset.url;
    if(!url||!card)return;
    const isMark=btn.classList.contains('js-mark');
    const isDel=btn.classList.contains('js-del');
    if(isMark){card.querySelector('.js-unread-badge')?.remove();}
    if(isDel){card.style.opacity='0.6';}
    try{
      const res=await fetch(url,{method:isMark?'PATCH':'DELETE',headers:{'Content-Type':'application/json','X-CSRF-TOKEN':csrf,'Accept':'application/json'},body:isMark?JSON.stringify({}):null});
      const data=await res.json();
      if(!res.ok||!data?.ok)throw new Error(data?.msg||'Error');
      if(isMark && card.dataset.unread==='1'){card.dataset.unread='0';updateCounters(0,-1);}
      if(isDel){
        const wasUnread=card.dataset.unread==='1';
        card.remove();updateCounters(-1,wasUnread?-1:0);
      }
    }catch(e){
      if(isMark&&!card.querySelector('.js-unread-badge')){
        const b=document.createElement('span');
        b.className='badge unread js-unread-badge';b.style.marginLeft='6px';b.textContent='No leída';
        card.querySelector('.alert-title')?.appendChild(b);
      }
      if(isDel)card.style.opacity='';
      alert('No se pudo completar la acción: '+(e?.message||'Error'));
    }
  });
})();
</script>
@endpush
