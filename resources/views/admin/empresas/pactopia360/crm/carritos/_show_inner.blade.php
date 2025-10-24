{{-- resources/views/admin/empresas/pactopia360/crm/carritos/_show_inner.blade.php --}}
@php
  /** Normalización */
  $row  = $row ?? $carrito ?? null;

  $tags = $row?->etiquetas ?? [];
  if (is_string($tags)) { $tags = json_decode($tags, true) ?: ($tags ? [$tags] : []); }
  $tags = is_array($tags) ? array_values(array_filter($tags, fn($t)=>trim((string)$t) !== '')) : [];

  $meta = $row?->meta ?? $row?->metadata ?? null;
  if (is_string($meta)) { $meta = json_decode($meta, true); }
  $meta = is_array($meta) ? $meta : null;

  $estado = strtolower((string)($row->estado ?? 'nuevo'));
  $moneda = strtoupper((string)($row->moneda ?? 'MXN'));
  $total  = (float)($row->total ?? 0);

  $map = [
    'abierto'    => ['is-open','🔵','Abierto'],
    'convertido' => ['is-converted','✅','Convertido'],
    'cancelado'  => ['is-cancelled','✖','Cancelado'],
    'nuevo'      => ['is-new','✨','Nuevo'],
  ];
  [$cls, $ico, $estadoLbl] = $map[$estado] ?? $map['nuevo'];

  /** Rutas seguras (solo si existen) */
  $rtIdx   = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.index')
             ? route('admin.empresas.pactopia360.crm.carritos.index') : null;
  $rtEdit  = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.edit')
             ? route('admin.empresas.pactopia360.crm.carritos.edit', $row?->id) : null;
  $rtDel   = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.destroy')
             ? route('admin.empresas.pactopia360.crm.carritos.destroy', $row?->id) : null;

  // Acciones de estado opcionales (si las implementas más adelante)
  $rtToOpen   = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.toOpen')
               ? route('admin.empresas.pactopia360.crm.carritos.toOpen', $row?->id) : null;
  $rtToConv   = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.toConverted')
               ? route('admin.empresas.pactopia360.crm.carritos.toConverted', $row?->id) : null;
  $rtToCancel = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.carritos.toCancelled')
               ? route('admin.empresas.pactopia360.crm.carritos.toCancelled', $row?->id) : null;

  // Posibles relacionados si luego existen
  $rtFacturas = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.facturas.index')
               ? route('admin.empresas.pactopia360.crm.facturas.index', ['q' => $row?->id]) : null;
  $rtContactos = \Illuminate\Support\Facades\Route::has('admin.empresas.pactopia360.crm.contactos.index')
               ? route('admin.empresas.pactopia360.crm.contactos.index', ['q' => $row?->email]) : null;

  // Posibles adjuntos desde meta (por ejemplo meta['files'] = [{name,url}] )
  $files = [];
  if ($meta && isset($meta['files']) && is_array($meta['files'])) {
    foreach ($meta['files'] as $f) {
      $name = (string)($f['name'] ?? $f['filename'] ?? basename((string)($f['url'] ?? '')));
      $url  = (string)($f['url'] ?? '');
      if ($url) $files[] = ['name'=>$name ?: 'archivo', 'url'=>$url];
    }
  }
@endphp

@if(!$row)
  <div class="alert alert-danger">No se recibió el carrito a mostrar.</div>
@else
<div class="carrito-show mod-carritos-show" data-id="{{ $row->id }}">
  {{-- ===== Header / Hero ===== --}}
  <section class="hero">
    <div class="hero-inner">
      <div class="titlebox">
        <div class="title-ico" aria-hidden="true">🛒</div>
        <div class="title">
          <h2>Carrito #{{ $row->id }}</h2>
          <small>{{ e($row->titulo ?? '—') }}</small>
        </div>
      </div>

      <div class="badges">
        <span class="status {{ $cls }}" title="{{ $estadoLbl }}">
          <span class="dot"></span> {{ $ico }} {{ $estadoLbl }}
        </span>
        <span class="pill mono" title="Total">
          ${{ number_format($total,2) }} {{ $moneda }}
        </span>
        <span class="pill" title="Creado">
          📅 {{ optional($row->created_at)->format('Y-m-d H:i') ?: '—' }}
        </span>
      </div>
    </div>

    <div class="hero-actions" role="group" aria-label="Acciones rápidas">
      @if($rtEdit)
        <a class="btn btn-primary" href="{{ $rtEdit }}">✏️ Editar</a>
      @endif

      <button class="btn" data-copy="#copy-carrito-url">🔗 Copiar URL</button>
      <input type="hidden" id="copy-carrito-url" value="{{ request()->fullUrl() }}">

      @if($rtToOpen)
        <a class="btn" href="{{ $rtToOpen }}" onclick="return confirm('¿Marcar como ABIERTO?')">🔵 Abrir</a>
      @endif
      @if($rtToConv)
        <a class="btn" href="{{ $rtToConv }}" onclick="return confirm('¿Marcar como CONVERTIDO?')">✅ Convertir</a>
      @endif
      @if($rtToCancel)
        <a class="btn btn-danger" href="{{ $rtToCancel }}" onclick="return confirm('¿Cancelar este carrito?')">✖ Cancelar</a>
      @endif

      @if($rtDel)
        <form method="post" action="{{ $rtDel }}" onsubmit="return confirm('¿Eliminar carrito #{{ $row->id }}?')">
          @csrf @method('DELETE')
          <button class="btn btn-danger" type="submit">🗑 Eliminar</button>
        </form>
      @endif

      @if($rtIdx)
        <a class="btn-ghost" href="{{ $rtIdx }}">← Volver</a>
      @endif
    </div>
  </section>

  {{-- ===== Grid principal ===== --}}
  <div class="grid">
    {{-- Columna izquierda --}}
    <div class="col">
      {{-- Datos generales --}}
      <div class="card">
        <div class="card-h">Datos generales</div>
        <div class="card-b def">
          <div><dt>ID</dt><dd class="mono">#{{ $row->id }}</dd></div>
          <div><dt>Título</dt><dd>{{ e($row->titulo) }}</dd></div>
          <div><dt>Estado</dt>
            <dd><span class="status {{ $cls }}"><span class="dot"></span> {{ $estadoLbl }}</span></dd>
          </div>
          <div><dt>Total</dt><dd class="mono">${{ number_format($total,2) }} {{ $moneda }}</dd></div>
          <div><dt>Origen</dt><dd>{{ e($row->origen ?? '—') }}</dd></div>
          <div><dt>Empresa</dt><dd>{{ e($row->empresa_slug ?? '—') }}</dd></div>
          <div><dt>Creado</dt><dd class="mono">{{ optional($row->created_at)->format('Y-m-d H:i') }}</dd></div>
          <div><dt>Actualizado</dt><dd class="mono">{{ optional($row->updated_at)->format('Y-m-d H:i') }}</dd></div>
        </div>
      </div>

      {{-- Contacto --}}
      @if(($row->cliente ?? null) || ($row->email ?? null) || ($row->telefono ?? null))
      <div class="card">
        <div class="card-h">Contacto</div>
        <div class="card-b def">
          @if(!empty($row->cliente))
            <div><dt>Cliente</dt><dd>{{ e($row->cliente) }}</dd></div>
          @endif
          @if(!empty($row->email))
            <div><dt>Email</dt>
              <dd>
                <a href="mailto:{{ e($row->email) }}">{{ e($row->email) }}</a>
                <button class="btn-xs" data-copy-val="{{ e($row->email) }}">Copiar</button>
              </dd>
            </div>
          @endif
          @if(!empty($row->telefono))
            <div><dt>Teléfono</dt>
              <dd>
                <a href="tel:{{ preg_replace('/\D+/','', $row->telefono) }}">{{ e($row->telefono) }}</a>
                <button class="btn-xs" data-copy-val="{{ e($row->telefono) }}">Copiar</button>
              </dd>
            </div>
          @endif
        </div>
      </div>
      @endif

      {{-- Etiquetas --}}
      <div class="card">
        <div class="card-h">Etiquetas</div>
        <div class="card-b">
          @if($tags)
            <div class="tags">
              @foreach($tags as $t)
                <span class="tag">#{{ e($t) }}</span>
              @endforeach
            </div>
          @else
            <div class="muted">Sin etiquetas.</div>
          @endif
        </div>
      </div>

      {{-- Notas --}}
      @if(!empty($row->notas))
      <div class="card">
        <div class="card-h">Notas</div>
        <div class="card-b">
          <div class="note">{!! nl2br(e($row->notas)) !!}</div>
        </div>
      </div>
      @endif

      {{-- Meta JSON (colapsable) --}}
      <details class="card" @if($meta) open @endif>
        <summary class="card-h">Metadatos (JSON)</summary>
        <div class="card-b">
          @if($meta)
            <div class="json-toolbar">
              <button class="btn-xs" data-json-toggle>Contraer/Expandir</button>
              <button class="btn-xs" data-copy="#json-meta-{{ $row->id }}">Copiar JSON</button>
              <input type="hidden" id="json-meta-{{ $row->id }}" value='@json($meta, JSON_UNESCAPED_UNICODE)'>
            </div>
            <pre class="pre json-view" data-collapsed="0">@json($meta, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE)</pre>
          @else
            <div class="muted">Sin metadatos.</div>
          @endif
        </div>
      </details>
    </div>

    {{-- Columna derecha --}}
    <div class="col">
      {{-- Acciones rápidas / utilidades --}}
      <div class="card">
        <div class="card-h">Acciones</div>
        <div class="card-b actions">
          @if($rtEdit)
            <a class="btn" href="{{ $rtEdit }}">✏️ Editar</a>
          @endif
          <button class="btn" data-copy-val="{{ (string)($row->id) }}">Copiar ID</button>
          <button class="btn" data-copy-val="{{ e((string)($row->titulo)) }}">Copiar Título</button>
          <button class="btn" data-copy="#copy-carrito-url">Copiar URL</button>
          @if($rtIdx)
            <a class="btn-ghost" href="{{ $rtIdx }}">← Volver al listado</a>
          @endif
        </div>
      </div>

      {{-- Adjuntos (si meta.files) --}}
      <div class="card">
        <div class="card-h">Adjuntos</div>
        <div class="card-b">
          @if($files)
            <ul class="files">
              @foreach($files as $f)
                <li><a href="{{ $f['url'] }}" target="_blank" rel="noopener">{{ e($f['name']) }}</a></li>
              @endforeach
            </ul>
          @else
            <div class="muted">Sin archivos adjuntos.</div>
          @endif
        </div>
      </div>

      {{-- Relacionados / Navegación contextual --}}
      <div class="card">
        <div class="card-h">Relacionados</div>
        <div class="card-b related">
          @if($rtContactos)
            <a class="btn" href="{{ $rtContactos }}">👥 Ver contactos relacionados</a>
          @endif
          @if($rtFacturas)
            <a class="btn" href="{{ $rtFacturas }}">🧾 Ver facturas relacionadas</a>
          @endif
          @unless($rtContactos || $rtFacturas)
            <div class="muted">Sin atajos disponibles.</div>
          @endunless
        </div>
      </div>

      {{-- Timeline simple (si expones $row->logs / activities en algún momento) --}}
      <div class="card">
        <div class="card-h">Historial</div>
        <div class="card-b">
          @php
            $logs = $row->logs ?? $row->activities ?? null; // colección opcional
          @endphp
          @if($logs && count($logs))
            <ol class="timeline">
              @foreach($logs as $log)
                @php
                  $ts = \Illuminate\Support\Carbon::parse($log->created_at ?? now())->format('Y-m-d H:i');
                  $msg = $log->message ?? $log->event ?? json_encode($log, JSON_UNESCAPED_UNICODE);
                @endphp
                <li>
                  <div class="t-dot"></div>
                  <div class="t-content">
                    <div class="t-time mono">{{ $ts }}</div>
                    <div class="t-text">{{ e($msg) }}</div>
                  </div>
                </li>
              @endforeach
            </ol>
          @else
            <div class="muted">Aún no hay historial para este carrito.</div>
          @endif
        </div>
      </div>
    </div>
  </div>
</div>
@endif

{{-- ===== Estilos encapsulados ===== --}}
<style>
  .mod-carritos-show{ --accent:#0b2a3a; --accent2:#394759; --muted:#6b7280; --border:rgba(0,0,0,.08); --card:#fff; --txt:#0f172a; }
  [data-theme="dark"] .mod-carritos-show{ --muted:#a1a1aa; --border:rgba(255,255,255,.12); --card:#0f172a; --txt:#e5e7eb; }

  .mod-carritos-show .hero{
    border:1px solid var(--border); border-radius:16px; overflow:hidden; margin-bottom:14px;
    background:
      radial-gradient(120% 120% at 0% 0%, color-mix(in oklab, var(--accent) 14%, transparent), transparent 58%),
      radial-gradient(120% 120% at 100% 0%, color-mix(in oklab, var(--accent2) 16%, transparent), transparent 60%),
      var(--card);
    box-shadow: 0 14px 34px rgba(0,0,0,.08);
  }
  .mod-carritos-show .hero-inner{ display:grid; grid-template-columns:1fr auto; gap:10px; padding:16px 14px; align-items:center }
  .mod-carritos-show .titlebox{ display:flex; gap:12px; align-items:center }
  .mod-carritos-show .title-ico{ width:40px; height:40px; display:grid; place-items:center; color:#fff; border-radius:12px;
    background:linear-gradient(135deg, var(--accent), color-mix(in oklab, var(--accent2) 70%, black 30%)) }
  .mod-carritos-show .title h2{ margin:0; font:800 18px/1.1 system-ui; color:var(--txt) }
  .mod-carritos-show .title small{ display:block; color:var(--muted) }

  .mod-carritos-show .badges{ display:flex; gap:8px; align-items:center; flex-wrap:wrap }
  .mod-carritos-show .pill{ display:inline-flex; align-items:center; gap:6px; padding:8px 10px; border-radius:999px; border:1px solid var(--border); background:var(--card); font:800 12px/1 system-ui; color:var(--txt) }
  .mod-carritos-show .mono{ font-variant-numeric: tabular-nums }

  .mod-carritos-show .hero-actions{ display:flex; gap:8px; flex-wrap:wrap; padding:10px 14px; border-top:1px solid var(--border); background: color-mix(in oklab, var(--card) 96%, transparent) }

  .mod-carritos-show .grid{ display:grid; gap:14px; grid-template-columns: 1fr 1fr }
  @media (max-width: 1100px){ .mod-carritos-show .grid{ grid-template-columns: 1fr } }

  .mod-carritos-show .card{ border:1px solid var(--border); background:var(--card); border-radius:14px; box-shadow:0 6px 18px rgba(0,0,0,.06) }
  .mod-carritos-show .card-h{ padding:10px 14px; font:700 13px/1 system-ui; border-bottom:1px solid var(--border); color:var(--txt) }
  .mod-carritos-show .card-b{ padding:12px 14px; color:var(--txt) }

  .mod-carritos-show .def{ display:grid; grid-template-columns: 160px 1fr; gap:8px 12px }
  .mod-carritos-show .def dt{ font-weight:600; color:var(--muted) }
  .mod-carritos-show .def dd{ margin:0 }

  .mod-carritos-show .status{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; font:800 12px/1 system-ui; border:1px solid transparent; color:var(--txt) }
  .mod-carritos-show .status .dot{ width:8px; height:8px; border-radius:999px; background:currentColor }
  .mod-carritos-show .is-open{ color:#1d4ed8; background: color-mix(in oklab, #1d4ed8 16%, transparent); border-color: color-mix(in oklab, #1d4ed8 32%, transparent) }
  .mod-carritos-show .is-new{ color:#0891b2; background: color-mix(in oklab, #0891b2 16%, transparent); border-color: color-mix(in oklab, #0891b2 32%, transparent) }
  .mod-carritos-show .is-converted{ color:#16a34a; background: color-mix(in oklab, #16a34a 16%, transparent); border-color: color-mix(in oklab, #16a34a 28%, transparent) }
  .mod-carritos-show .is-cancelled{ color:#ef4444; background: color-mix(in oklab, #ef4444 14%, transparent); border-color: color-mix(in oklab, #ef4444 30%, transparent) }

  .mod-carritos-show .btn, .mod-carritos-show .btn-ghost, .mod-carritos-show .btn-xs{
    display:inline-flex; align-items:center; gap:6px; padding:8px 10px; border-radius:10px; border:1px solid var(--border);
    background:var(--card); color:var(--txt); font:800 12px/1 system-ui; text-decoration:none; cursor:pointer
  }
  .mod-carritos-show .btn:hover{ filter:brightness(1.06) }
  .mod-carritos-show .btn-primary{ color:#fff; border:0; background:linear-gradient(135deg, var(--accent), color-mix(in oklab, var(--accent2) 72%, black 28%)) }
  .mod-carritos-show .btn-danger{ color:#fff; border:0; background:linear-gradient(135deg, #ff2a2a, color-mix(in oklab, #ff2a2a 70%, black 30%)) }
  .mod-carritos-show .btn-ghost{ background:transparent }

  .mod-carritos-show .btn-xs{ padding:6px 8px; font-size:11px }

  .mod-carritos-show .tags{ display:flex; gap:8px; flex-wrap:wrap }
  .mod-carritos-show .tag{ display:inline-flex; align-items:center; gap:6px; padding:6px 10px; border-radius:999px; border:1px dashed var(--border); background:color-mix(in oklab, var(--card) 94%, transparent); font:700 12px/1 system-ui }

  .mod-carritos-show .note{ white-space:pre-wrap }
  .mod-carritos-show .pre{ padding:10px; border-radius:10px; background:rgba(0,0,0,.05); overflow:auto }
  [data-theme="dark"] .mod-carritos-show .pre{ background:rgba(255,255,255,.08) }

  .mod-carritos-show .json-toolbar{ display:flex; gap:8px; margin-bottom:8px; align-items:center }
  .mod-carritos-show .json-view[data-collapsed="1"]{ max-height:140px; overflow:auto }

  .mod-carritos-show .files{ margin:0; padding-left:18px }
  .mod-carritos-show .files li{ margin:2px 0 }

  .mod-carritos-show .related{ display:flex; gap:8px; flex-wrap:wrap }

  .mod-carritos-show .timeline{ list-style:none; margin:0; padding:0 }
  .mod-carritos-show .timeline li{ display:grid; grid-template-columns: 18px 1fr; gap:8px; position:relative; padding:8px 0 }
  .mod-carritos-show .timeline .t-dot{ width:10px; height:10px; border-radius:999px; background:color-mix(in oklab, var(--accent) 70%, black 30%); margin-top:6px }
  .mod-carritos-show .timeline .t-content{ border-left:2px solid var(--border); padding-left:10px }
  .mod-carritos-show .timeline .t-time{ color:var(--muted); font-size:12px; margin-bottom:2px }
  .mod-carritos-show .timeline .t-text{ color:var(--txt) }
</style>

{{-- ===== Scripts mínimos (copiar al portapapeles y JSON toggle) ===== --}}
<script>
(function(){
  'use strict';
  const qs  = (s,ctx=document)=>ctx.querySelector(s);
  const qsa = (s,ctx=document)=>Array.from(ctx.querySelectorAll(s));

  // Copiar desde data-copy="#id" o data-copy-val="texto"
  qsa('[data-copy],[data-copy-val]').forEach(btn=>{
    if (btn.dataset.bound) return; btn.dataset.bound='1';
    btn.addEventListener('click', async ()=>{
      try{
        let text = '';
        if (btn.dataset.copy) {
          const el = qs(btn.dataset.copy);
          if (el) text = (el.value ?? el.textContent ?? '').toString();
        } else {
          text = (btn.dataset.copyVal ?? '').toString();
        }
        await navigator.clipboard.writeText(text);
        btn.textContent = '✅ Copiado';
        setTimeout(()=>{ btn.textContent = btn.classList.contains('btn-xs') ? 'Copiar' : (btn.textContent.includes('URL')?'Copiar URL':'Copiar'); }, 1200);
      }catch(e){ alert('No se pudo copiar'); }
    }, {passive:true});
  });

  // Toggle JSON colapsado
  qsa('[data-json-toggle]').forEach(tg=>{
    if (tg.dataset.bound) return; tg.dataset.bound='1';
    tg.addEventListener('click', ()=>{
      const pre = tg.closest('.card-b')?.querySelector('.json-view');
      if (!pre) return;
      const cur = pre.getAttribute('data-collapsed') === '1';
      pre.setAttribute('data-collapsed', cur ? '0' : '1');
    });
  });
})();
</script>
