{{-- resources/views/admin/sat/ops/credentials/index.blade.php --}}
{{-- P360 · Admin · SAT Ops · Credenciales (v4.0 · UI scalable + CSS/JS external + row expand + consistent actions) --}}

@extends('layouts.admin')

@section('title', $title ?? 'SAT · Operación · Credenciales')
@section('pageClass','page-admin-sat-ops page-admin-sat-ops-credentials')

@php
  use Illuminate\Support\Carbon;
  use Illuminate\Support\Facades\Route;

  $backUrl = Route::has('admin.sat.ops.index') ? route('admin.sat.ops.index') : url('/admin');

  $rows   = $rows ?? null;
  $q      = $q ?? '';
  $status = $status ?? '';
  $origin = $origin ?? '';
  $per    = (int)($per ?? 25);

  $pick = function($row, array $keys, $default = null) {
    foreach ($keys as $k) {
      $v = data_get($row, $k);
      if ($v !== null && $v !== '') return $v;
    }
    return $default;
  };

  $fmt = function($v, $dash='—'){
    $v = trim((string)$v);
    return $v !== '' ? $v : $dash;
  };

  $fmtRfc = function ($v) use ($fmt) {
    $v = strtoupper(trim((string) $v));
    return $fmt($v);
  };

  $fmtDate = function($dt, $withTime=false){
    try{
      if(!$dt) return '—';
      $c = $dt instanceof \DateTimeInterface ? Carbon::instance($dt) : Carbon::parse((string)$dt);
      return $withTime ? $c->format('Y-m-d H:i') : $c->format('Y-m-d');
    }catch(\Throwable){
      return '—';
    }
  };

  $ago = function($dt){
    try{
      if(!$dt) return '—';
      $c = $dt instanceof \DateTimeInterface ? Carbon::instance($dt) : Carbon::parse((string)$dt);
      return $c->diffForHumans();
    }catch(\Throwable){
      return '—';
    }
  };

  $badge = function($row) use ($pick) {
    $isValid = (bool) $pick($row, ['is_valid'], false);
    $isBlock = (bool) $pick($row, ['is_blocked'], false);
    $stRaw   = strtolower(trim((string)$pick($row, ['sat_status'], '')));

    if ($isBlock || in_array($stRaw, ['bloqueado','blocked','error','invalid'], true)) {
      return ['Bloqueado', 'b-bad'];
    }
    if ($isValid || in_array($stRaw, ['validado','valido','ok','active'], true)) {
      return ['Validado', 'b-ok'];
    }
    return ['Pendiente', 'b-warn'];
  };

  $originTag = function($row) use ($pick) {
    $extRfc = trim((string)$pick($row, ['external_rfc'], ''));
    $isExt  = (bool)$pick($row, ['from_external'], false);
    $extVer = (bool)$pick($row, ['external_verified'], false);

    if ($extRfc !== '' || $isExt) {
      return $extVer ? ['Externo · verificado','t-ext-ok'] : ['Externo','t-ext'];
    }
    return ['Cliente','t-cli'];
  };

  $fileTag = function($row) use ($pick){
    $cer = (string)$pick($row, ['cer_path'], '');
    $key = (string)$pick($row, ['key_path'], '');
    $has = (trim($cer) !== '' && trim($key) !== '');
    return $has ? ['CER/KEY','f-ok'] : ['Sin archivos','f-warn'];
  };

  $alertTag = function($row) use ($pick){
    $e = (int)$pick($row, ['alert_email'], 0);
    $w = (int)$pick($row, ['alert_whatsapp'], 0);
    $i = (int)$pick($row, ['alert_inapp'], 0);

    $parts = [];
    if($e) $parts[] = 'Email';
    if($w) $parts[] = 'WhatsApp';
    if($i) $parts[] = 'InApp';

    if(empty($parts)) return ['Alertas: Off','al-off'];
    return ['Alertas: '.implode(' · ', $parts),'al-on'];
  };

  $short = function($v, $n=12){
    $v = (string)$v;
    if($v === '' || $v === '—') return '—';
    return strlen($v) > $n ? (substr($v,0,$n).'…') : $v;
  };

  $kpPlain = function($row) use ($pick){
    $enc = (string)$pick($row, ['key_password_enc'], '');
    $raw = (string)$pick($row, ['key_password'], '');

    $try = function($v){
      $v = trim((string)$v);
      if($v === '') return '';
      try { return (string) decrypt($v); } catch (\Throwable) {}
      try {
        $b = base64_decode($v, true);
        if($b !== false && $b !== '') return (string)$b;
      } catch (\Throwable) {}
      return $v;
    };

    $out = $try($enc);
    if($out === '') $out = $try($raw);
    return trim($out) !== '' ? $out : '';
  };

  $canCer = Route::has('admin.sat.ops.credentials.cer');
  $canKey = Route::has('admin.sat.ops.credentials.key');

  $canGoAccount = Route::has('admin.accounts.show'); // si existe en tu proyecto
@endphp

@section('page-header')
  <div class="p360-ph">
    <div class="p360-ph-left">
      <div class="p360-ph-kicker">ADMIN · SAT OPS</div>
      <h1 class="p360-ph-title">Credenciales</h1>
      <div class="p360-ph-sub">Control operativo: origen, cuenta, archivos, alertas y estatus SAT.</div>
    </div>

    <div class="p360-ph-right">
      <a class="p360-btn" href="{{ $backUrl }}">← Volver</a>
      <button type="button" class="p360-btn" data-p360-reload>Refrescar</button>
    </div>
  </div>
@endsection

@section('content')

  <div class="ops-wrap" id="p360SatOpsCreds"
       data-can-cer="{{ $canCer ? '1' : '0' }}"
       data-can-key="{{ $canKey ? '1' : '0' }}">

    {{-- Toolbar / Filters --}}
    <form class="ops-toolbar" method="GET" action="{{ url()->current() }}" data-p360-filters>
      <div class="ops-search">
        <div class="lbl">Buscar</div>
        <div class="inpwrap">
          <input id="opsSearch" name="q" class="inp" type="search"
                 value="{{ $q }}" placeholder="RFC, razón social, cuenta_id, ID, externo..."
                 autocomplete="off" data-p360-search>
          <button class="kbtn" type="button" title="Atajo: /" data-p360-focus-search>/</button>
        </div>
        <div class="ops-hint">Tip: presiona <span class="kbd">/</span> para buscar, <span class="kbd">Esc</span> cierra paneles.</div>
      </div>

      <div class="ops-filters">
        <div class="ops-select">
          <div class="lbl">Estatus</div>
          <select name="status" class="sel">
            <option value=""          @selected($status==='')>Todos</option>
            <option value="validado"  @selected($status==='validado')>Validado</option>
            <option value="pendiente" @selected($status==='pendiente')>Pendiente</option>
            <option value="bloqueado" @selected($status==='bloqueado')>Bloqueado</option>
          </select>
        </div>

        <div class="ops-select">
          <div class="lbl">Origen</div>
          <select name="origin" class="sel">
            <option value=""         @selected($origin==='')>Todos</option>
            <option value="cliente"  @selected($origin==='cliente')>Cliente</option>
            <option value="externo"  @selected($origin==='externo')>Externo</option>
          </select>
        </div>

        <div class="ops-select ops-select-sm">
          <div class="lbl">Por pág.</div>
          <select name="per" class="sel">
            @foreach([10,25,50,100,200] as $n)
              <option value="{{ $n }}" @selected((int)$per===$n)>{{ $n }}</option>
            @endforeach
          </select>
        </div>

        <div class="ops-actions-right">
          <button class="p360-btn p360-btn-primary" type="submit">Aplicar</button>
          <a class="p360-btn" href="{{ url()->current() }}">Limpiar</a>
        </div>
      </div>
    </form>

    {{-- Summary --}}
    <div class="ops-summary">
      <div class="sum-left">
        <div class="sum-title">Resultados</div>
        <div class="sum-sub">
          @if($rows)
            Mostrando {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} de {{ $rows->total() }}
          @else
            —
          @endif
        </div>
      </div>

      <div class="sum-right">
        <span class="chip">Ruta: <span class="mono">/admin/sat/ops/credentials</span></span>
        <span class="chip">Fuente: <span class="mono">mysql_clientes.sat_credentials</span></span>
        <button class="chip chip-btn" type="button" data-p360-collapse-all>Contraer</button>
        <button class="chip chip-btn" type="button" data-p360-expand-all>Expandir</button>
      </div>
    </div>

    {{-- List --}}
    <div class="ops-table ops-v4">
      <div class="ops-table-head">
        <div class="th">RFC / Razón social</div>
        <div class="th">Cuenta</div>
        <div class="th">Origen</div>
        <div class="th">Archivos</div>
        <div class="th">Alertas</div>
        <div class="th">Actividad</div>
        <div class="th th-actions">Acciones</div>
      </div>

      @if(!$rows || $rows->count() === 0)
        <div class="ops-empty">
          <div class="empty-ico">🗂️</div>
          <div class="empty-title">Sin resultados</div>
          <div class="empty-sub">Ajusta filtros o limpia la búsqueda.</div>
        </div>
      @else
        @foreach($rows as $row)
          @php
            $id     = (string) data_get($row,'id');
            $rfc    = $fmtRfc(data_get($row,'rfc'));
            $name   = $fmt($pick($row, ['razon_social'], '—'));

            $aidFull = $fmt($pick($row, ['account_id'], '—'));
            $cidFull = $fmt($pick($row, ['cuenta_id'], '—'));

            $aid = $short($aidFull, 12);
            $cid = $short($cidFull, 12);

            [$bTxt, $bCls] = $badge($row);
            [$oTxt, $oCls] = $originTag($row);
            [$fTxt, $fCls] = $fileTag($row);
            [$alTxt,$alCls]= $alertTag($row);

            $extRfc = strtoupper(trim((string)$pick($row, ['external_rfc'], '')));
            $extVer = (bool)$pick($row, ['external_verified'], false);
            $originHint = $extRfc !== '' ? ('RFC externo: '.$extRfc.($extVer ? ' (verificado)' : '')) : '';

            $validatedAt = $pick($row, ['validated_at'], null);
            $createdAt   = $pick($row, ['created_at'], null);
            $updatedAt   = $pick($row, ['updated_at'], null);
            $lastAlertAt = $pick($row, ['last_alert_at'], null);

            $keyPass = $kpPlain($row);
            $hasPass = ($keyPass !== '');

            $metaRaw = $pick($row, ['meta'], null);
            try{
              if (is_string($metaRaw)) $metaJson = $metaRaw;
              else $metaJson = json_encode($metaRaw, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }catch(\Throwable){
              $metaJson = (string)$metaRaw;
            }
            $metaJson = $metaJson ?: '{}';

            $accountUrl = ($canGoAccount && $aidFull !== '—') ? route('admin.accounts.show', ['account' => $aidFull]) : null;

            // valores completos para JS
            $payload = [
              'id' => $id,
              'rfc' => $rfc,
              'name' => $name,
              'account_id' => $aidFull,
              'cuenta_id' => $cidFull,
              'external_rfc' => $extRfc,
              'password' => $keyPass,
              'meta' => $metaJson,
              'cer_url' => $canCer ? route('admin.sat.ops.credentials.cer', ['id' => $id]) : null,
              'key_url' => $canKey ? route('admin.sat.ops.credentials.key', ['id' => $id]) : null,
            ];
          @endphp

          <div class="tr" data-row data-row-id="{{ e($id) }}" data-row-payload='@json($payload)'>
            {{-- MAIN --}}
            <div class="td td-main">
              <div class="main-top">
                <button class="row-toggle" type="button" data-row-toggle aria-label="Expandir">+</button>
                <div class="rfc mono">{{ $rfc }}</div>
                <span class="pill {{ $bCls }}">{{ $bTxt }}</span>
              </div>

              <div class="main-name">{{ $name }}</div>

              <div class="main-sub">
                <span class="mini mono" title="ID completo">{{ $id }}</span>

                @if($validatedAt)
                  <span class="dotsep">•</span>
                  <span class="mini" title="Validado">{{ $fmtDate($validatedAt, true) }}</span>
                @endif

                @if($accountUrl)
                  <span class="dotsep">•</span>
                  <a class="link" href="{{ $accountUrl }}">Ver cuenta</a>
                @endif
              </div>
            </div>

            {{-- ACCOUNT --}}
            <div class="td td-account">
              <div class="kv">
                <div class="k">account_id</div>
                <div class="v mono" title="{{ $aidFull }}">{{ $aid }}</div>
              </div>
              <div class="kv">
                <div class="k">cuenta_id</div>
                <div class="v mono" title="{{ $cidFull }}">{{ $cid }}</div>
              </div>

              <div class="mini-actions">
                <button class="a a-ghost" type="button" data-copy="account_id">Copiar account</button>
                <button class="a a-ghost" type="button" data-copy="cuenta_id">Copiar cuenta</button>
              </div>
            </div>

            {{-- ORIGIN --}}
            <div class="td td-origin">
              <span class="tag {{ $oCls }}" title="{{ $originHint }}">{{ $oTxt }}</span>
              @if($extRfc !== '')
                <div class="hint mono">{{ $extRfc }}</div>
              @else
                <div class="hint">—</div>
              @endif
            </div>

            {{-- FILES --}}
            <div class="td td-files">
              <span class="tag {{ $fCls }}">{{ $fTxt }}</span>

              <div class="files-mini">
                <span class="mini {{ trim((string)$pick($row,['cer_path'],'') )!=='' ? 'ok' : 'warn' }}">CER</span>
                <span class="mini {{ trim((string)$pick($row,['key_path'],'') )!=='' ? 'ok' : 'warn' }}">KEY</span>
              </div>

              <div class="passbox">
                <div class="k">Password</div>
                @if($hasPass)
                  <div class="passrow">
                    <input class="passinp" type="password" value="{{ $keyPass }}" readonly data-pass-input>
                    <button class="a a-ghost" type="button" data-pass-toggle>Ver</button>
                    <button class="a a-ghost" type="button" data-copy="password">Copiar</button>
                  </div>
                @else
                  <div class="hint">—</div>
                @endif
              </div>
            </div>

            {{-- ALERTS --}}
            <div class="td td-status">
              <span class="tag {{ $alCls }}">{{ $alTxt }}</span>
              @if($lastAlertAt)
                <div class="hint" title="Última alerta">{{ $ago($lastAlertAt) }}</div>
              @else
                <div class="hint">—</div>
              @endif
            </div>

            {{-- ACTIVITY --}}
            <div class="td td-meta">
              <div class="kv">
                <div class="k">Alta</div>
                <div class="v" title="{{ $fmtDate($createdAt, true) }}">{{ $ago($createdAt) }}</div>
              </div>
              <div class="kv">
                <div class="k">Actualización</div>
                <div class="v" title="{{ $fmtDate($updatedAt, true) }}">{{ $ago($updatedAt) }}</div>
              </div>
            </div>

            {{-- ACTIONS --}}
            <div class="td td-actions">
              <div class="act">
                @if($canCer)
                  <a class="a" href="{{ route('admin.sat.ops.credentials.cer', ['id' => $id]) }}" title="Descargar CER">CER</a>
                @endif
                @if($canKey)
                  <a class="a" href="{{ route('admin.sat.ops.credentials.key', ['id' => $id]) }}" title="Descargar KEY">KEY</a>
                @endif

                <button class="a a-ghost" type="button" data-copy="rfc">Copiar RFC</button>
                <button class="a a-ghost" type="button" data-copy="id">Copiar ID</button>

                <button class="a" type="button" data-meta-open>
                  Ver meta
                </button>
              </div>
            </div>

            {{-- EXPANDABLE DETAILS (collapsed by default) --}}
            <div class="td td-expand" data-row-expand>
              <div class="expand-grid">
                <div class="expand-card">
                  <div class="ec-title">Identificadores</div>
                  <div class="ec-row"><span class="ec-k">ID</span><span class="ec-v mono">{{ $id }}</span></div>
                  <div class="ec-row"><span class="ec-k">account_id</span><span class="ec-v mono">{{ $aidFull }}</span></div>
                  <div class="ec-row"><span class="ec-k">cuenta_id</span><span class="ec-v mono">{{ $cidFull }}</span></div>
                </div>

                <div class="expand-card">
                  <div class="ec-title">Origen</div>
                  <div class="ec-row"><span class="ec-k">Tipo</span><span class="ec-v">{{ $oTxt }}</span></div>
                  <div class="ec-row"><span class="ec-k">RFC externo</span><span class="ec-v mono">{{ $extRfc !== '' ? $extRfc : '—' }}</span></div>
                  <div class="ec-row"><span class="ec-k">Verificado</span><span class="ec-v">{{ $extRfc !== '' ? ($extVer ? 'Sí' : 'No') : '—' }}</span></div>
                </div>

                <div class="expand-card">
                  <div class="ec-title">Acciones rápidas</div>
                  <div class="ec-actions">
                    <button class="a a-ghost" type="button" data-copy="account_id">Copiar account_id</button>
                    <button class="a a-ghost" type="button" data-copy="cuenta_id">Copiar cuenta_id</button>
                    <button class="a a-ghost" type="button" data-meta-open>Ver meta</button>
                    <button class="a a-ghost" type="button" data-row-collapse>Contraer</button>
                  </div>
                </div>
              </div>
            </div>

          </div>
        @endforeach
      @endif
    </div>

    {{-- Pagination --}}
    @if($rows && $rows->hasPages())
      <div class="ops-pager">
        {{ $rows->links() }}
      </div>
    @endif

  </div>

  {{-- Meta Drawer --}}
  <div class="p360-drawer" id="metaDrawer" aria-hidden="true">
    <div class="drawer-backdrop" data-drawer-close></div>
    <div class="drawer-card" role="dialog" aria-modal="true" aria-label="Meta">
      <div class="drawer-head">
        <div class="drawer-title" id="metaTitle">Meta</div>
        <button type="button" class="drawer-x" data-drawer-close aria-label="Cerrar">×</button>
      </div>
      <div class="drawer-body">
        <pre class="drawer-pre" id="metaPre">{}</pre>
      </div>
      <div class="drawer-foot">
        <button type="button" class="p360-btn" data-copy-meta>Copiar</button>
        <button type="button" class="p360-btn p360-btn-primary" data-drawer-close>Cerrar</button>
      </div>
    </div>
  </div>

@endsection

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/admin/css/sat-ops-credentials.css') }}">
@endpush

@push('scripts')
  <script>
    // Config mínimo (sin lógica). Evita depender de inline handlers por fila.
    window.P360_SAT_OPS_CREDS = window.P360_SAT_OPS_CREDS || {};
    window.P360_SAT_OPS_CREDS.toast = !!(window.P360 && typeof window.P360.toast === 'function');
  </script>
  <script src="{{ asset('assets/admin/js/sat-ops-credentials.js') }}"></script>
@endpush
