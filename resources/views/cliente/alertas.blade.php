{{-- resources/views/cliente/alertas.blade.php --}}
@extends('layouts.client')
@section('title','Notificaciones · Pactopia360')

@push('styles')
<style>
  .page-header{ display:flex; align-items:center; justify-content:space-between; gap:12px; flex-wrap:wrap; margin-bottom:14px }
  .page-title{ margin:0; font-size:clamp(18px,2.4vw,22px); font-weight:900; color:var(--text) }
  .muted{ color:var(--muted) }
  .toolbar{ display:flex; align-items:center; gap:8px; flex-wrap:wrap }
  .segmented{ display:inline-flex; border:1px solid var(--border); border-radius:999px; overflow:hidden; background:var(--chip) }
  .segmented a{ padding:8px 12px; font-weight:800; text-decoration:none; color:var(--text) }
  .segmented a.active{ background:color-mix(in oklab, var(--blue) 22%, transparent) }
  .input, .select{
    display:inline-flex; align-items:center; gap:8px; height:38px; padding:0 12px; border-radius:12px;
    border:1px solid var(--border); background:var(--chip); color:var(--text); font-weight:700
  }
  .select select, .input input{ all:unset }
  .counter-badge{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px solid var(--border); background:var(--chip); font-weight:900; }
  .list{ display:flex; flex-direction:column; gap:10px }
  .card{ background:var(--card); border:1px solid var(--border); border-radius:14px; padding:12px; box-shadow:var(--shadow) }
  .alert-item{ display:grid; grid-template-columns: 28px 1fr auto; gap:10px; align-items:flex-start }
  .alert-ico{ width:22px; height:22px; display:grid; place-items:center; border-radius:8px; border:1px solid var(--border); background:var(--chip) }
  .alert-title{ margin:0; font-size:14px; font-weight:900 }
  .alert-desc{ margin:4px 0 0; font-size:14px; line-height:1.45 }
  .meta{ display:flex; gap:10px; align-items:center; white-space:nowrap; color:var(--muted); font-size:12px; }
  .badge{ display:inline-flex; align-items:center; gap:6px; padding:4px 8px; border-radius:999px; border:1px solid var(--border); font-weight:800; font-size:12px }
  .badge.unread{ background:color-mix(in oklab, var(--blue) 20%, transparent); border-color:color-mix(in oklab, var(--blue) 40%, transparent) }
  .badge.type-info{ background:color-mix(in oklab, #38bdf8 18%, transparent); border-color:color-mix(in oklab, #38bdf8 36%, transparent) }
  .badge.type-success{ background:color-mix(in oklab, #16a34a 18%, transparent); border-color:color-mix(in oklab, #16a34a 36%, transparent) }
  .badge.type-warning{ background:color-mix(in oklab, #f59e0b 18%, transparent); border-color:color-mix(in oklab, #f59e0b 36%, transparent) }
  .badge.type-error{ background:color-mix(in oklab, #ef4444 18%, transparent); border-color:color-mix(in oklab, #ef4444 36%, transparent) }
  .actions .btn{ display:inline-flex; align-items:center; justify-content:center; width:36px; height:36px; border-radius:10px; border:1px solid var(--border); background:var(--chip); cursor:pointer }
</style>
@endpush

@section('content')
@php
  $notifs = $notifs ?? null; // paginator opcional
  $rows   = $notifs ? $notifs->items() : ($items ?? []);

  $status = $status ?? 'unread';
  $totalCount  = $notifs ? (int) $notifs->total() : (is_countable($rows) ? count($rows) : 0);
  $unreadCount = (int) ($notifCount ?? 0);

  $mkUrl = function($st) { return request()->fullUrlWithQuery(['status'=>$st, 'page'=>1]); };

  $get = function($n, $keys, $default = null) {
    foreach ((array)$keys as $k) {
      if (is_array($n) && array_key_exists($k, $n) && $n[$k] !== null) return $n[$k];
      if (is_object($n) && isset($n->{$k}) && $n->{$k} !== null) return $n->{$k};
    }
    return $default;
  };
@endphp

<div class="page-header">
  <div>
    <h1 class="page-title">Notificaciones</h1>
    <div class="muted">Alertas de todo el ERP del cliente</div>
  </div>

  <div class="toolbar">
    <div class="segmented" role="tablist" aria-label="Filtro de estado">
      <a href="{{ $mkUrl('unread') }}" class="{{ $status==='unread'?'active':'' }}" role="tab" aria-selected="{{ $status==='unread'?'true':'false' }}">No leídas</a>
      <a href="{{ $mkUrl('read') }}"   class="{{ $status==='read'  ?'active':'' }}" role="tab" aria-selected="{{ $status==='read'  ?'true':'false' }}">Leídas</a>
      <a href="{{ $mkUrl('all') }}"    class="{{ $status==='all'   ?'active':'' }}" role="tab" aria-selected="{{ $status==='all'   ?'true':'false' }}">Todas</a>
    </div>

    <form method="GET" action="{{ url()->current() }}" class="toolbar">
      @foreach(request()->except('per_page') as $k => $v)
        <input type="hidden" name="{{ $k }}" value="{{ is_array($v)?json_encode($v):$v }}">
      @endforeach
      <div class="select">
        <select name="per_page" onchange="this.form.submit()">
          @foreach([10,15,20,30,50,100] as $pp)
            <option value="{{ $pp }}" {{ (int)request('per_page', 15)===$pp?'selected':'' }}>{{ $pp }}/pág.</option>
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
        $tipo   = strtolower((string) ($get($n, ['tipo','type'], 'info')));
        $titulo = trim((string) ($get($n, ['titulo','title','asunto'], 'Notificación')));
        $desc   = trim((string) ($get($n, ['descripcion','description','mensaje','message','body'], 'Sin descripción.')));
        $fecha  = $get($n, ['created_at','fecha','createdAt','ts'], null);
        $id     = $get($n, ['id','uuid'], null);

        $leidaBool = $get($n, ['leida'], null);
        $readAt    = $get($n, ['read_at'], null);
        $isUnread  = is_null($readAt) && ($leidaBool === null || $leidaBool === false);

        $typeClass = 'type-info';
        if (str_contains($tipo, 'warn')) $typeClass = 'type-warning';
        elseif (str_contains($tipo, 'error') || str_contains($tipo,'danger') ) $typeClass = 'type-error';
        elseif (str_contains($tipo, 'ok') || str_contains($tipo,'success'))  $typeClass = 'type-success';

        try { $fechaTxt = $fecha ? \Carbon\Carbon::parse($fecha)->format('Y-m-d H:i') : ''; }
        catch (\Throwable $e) { $fechaTxt = (string) $fecha; }

        $urlRead   = $id !== null ? route('cliente.alertas.read', $id)   : null;
        $urlDelete = $id !== null ? route('cliente.alertas.delete', $id) : null;
      @endphp

      <div class="card alert-item" data-id="{{ $id }}" data-unread="{{ $isUnread ? '1' : '0' }}">
        <div class="alert-ico" aria-hidden="true">
          @if($typeClass==='type-success') ✅
          @elseif($typeClass==='type-warning') ⚠️
          @elseif($typeClass==='type-error') ❗
          @else ℹ️
          @endif
        </div>

        <div>
          <h4 class="alert-title">
            {{ $titulo }}
            <span class="badge {{ $typeClass }}" style="margin-left:6px; text-transform:uppercase">{{ strtoupper($tipo ?: 'info') }}</span>
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

        <div class="actions" style="display:flex; gap:6px; align-items:center;">
          <button class="btn js-mark"  title="Marcar como leída" {{ $urlRead ? '' : 'disabled' }} data-url="{{ $urlRead }}">✓</button>
          <button class="btn js-del"   title="Eliminar"           {{ $urlDelete ? '' : 'disabled' }} data-url="{{ $urlDelete }}">🗑️</button>
        </div>
      </div>
    @endforeach
  </div>

  @if($notifs && $notifs->hasPages())
    <div class="pagination">
      @if ($notifs->onFirstPage())
        <span>&laquo;</span>
      @else
        <a href="{{ $notifs->appends(request()->except('page'))->previousPageUrl() }}">&laquo;</a>
      @endif

      @foreach ($notifs->getUrlRange(max(1, $notifs->currentPage()-2), min($notifs->lastPage(), $notifs->currentPage()+2)) as $page => $url)
        @if ($page == $notifs->currentPage())
          <span class="active">{{ $page }}</span>
        @else
          <a href="{{ $url }}">{{ $page }}</a>
        @endif
      @endforeach

      @if ($notifs->hasMorePages())
        <a href="{{ $notifs->appends(request()->except('page'))->nextPageUrl() }}">&raquo;</a>
      @else
        <span>&raquo;</span>
      @endif
    </div>
  @endif
@endif
@endsection

@push('scripts')
<script>
(function(){
  const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
  const list = document.getElementById('alertsList');
  const elTotal  = document.getElementById('cntTotal');
  const elUnread = document.getElementById('cntUnread');

  function updateCounters(deltaTotal, deltaUnread){
    const toInt = (el) => parseInt((el?.textContent || '0').replace(/[^\d\-]/g,''), 10) || 0;
    if (elTotal){  elTotal.textContent  = (toInt(elTotal)  + (deltaTotal  || 0)).toLocaleString(); }
    if (elUnread){ elUnread.textContent = (toInt(elUnread) + (deltaUnread || 0)).toLocaleString(); }
  }

  if (!list) return;

  list.addEventListener('click', async (ev) => {
    const btn = ev.target.closest('.js-mark, .js-del');
    if (!btn) return;

    const card = btn.closest('.alert-item');
    const url  = btn.getAttribute('data-url');
    if (!url || !card) return;

    const isMark = btn.classList.contains('js-mark');
    const isDel  = btn.classList.contains('js-del');

    // Optimista
    if (isMark) {
      const badge = card.querySelector('.js-unread-badge');
      if (badge) badge.remove();
    }

    if (isDel) {
      card.style.opacity = '0.6';
    }

    try {
      const res = await fetch(url, {
        method: isMark ? 'PATCH' : 'DELETE',
        headers: {
          'Content-Type': 'application/json',
          'X-CSRF-TOKEN': csrf,
          'Accept': 'application/json',
        },
        body: isMark ? JSON.stringify({}) : null,
      });
      const data = await res.json();

      if (!res.ok || !data?.ok) {
        throw new Error(data?.msg || 'Error');
      }

      if (isMark) {
        // Si estaba no leída, decrementa contador
        if (card.dataset.unread === '1') {
          card.dataset.unread = '0';
          updateCounters(0, -1);
        }
      }

      if (isDel) {
        // Ajusta contadores: total -1 y, si estaba no leída, unread -1
        const wasUnread = card.dataset.unread === '1';
        card.remove();
        updateCounters(-1, wasUnread ? -1 : 0);
      }

    } catch(e) {
      // Rollback simple en error
      if (isMark) {
        const exists = card.querySelector('.js-unread-badge');
        if (!exists) {
          const b = document.createElement('span');
          b.className = 'badge unread js-unread-badge';
          b.style.marginLeft = '6px';
          b.textContent = 'No leída';
          card.querySelector('.alert-title')?.appendChild(b);
        }
      }
      if (isDel) {
        card.style.opacity = '';
      }
      alert('No se pudo completar la acción: ' + (e?.message || 'Error'));
    }
  });
})();
</script>
@endpush
