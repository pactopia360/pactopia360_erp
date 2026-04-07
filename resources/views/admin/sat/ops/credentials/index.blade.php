{{-- resources/views/admin/sat/ops/credentials/index.blade.php --}}
{{-- P360 · Admin · SAT Ops · Credenciales (v5.0 · Rediseño premium operativo) --}}

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
    $v = strtoupper(trim((string)$v));
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

  $short = function($v, $n=14){
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

  $rtCerName = Route::has('admin.sat.ops.credentials.cer') ? 'admin.sat.ops.credentials.cer'
             : (Route::has('admin.sat.credentials.cer') ? 'admin.sat.credentials.cer' : null);

  $rtKeyName = Route::has('admin.sat.ops.credentials.key') ? 'admin.sat.ops.credentials.key'
             : (Route::has('admin.sat.credentials.key') ? 'admin.sat.credentials.key' : null);

  $rtDeleteName = Route::has('admin.sat.ops.credentials.destroy') ? 'admin.sat.ops.credentials.destroy'
               : (Route::has('admin.sat.credentials.destroy') ? 'admin.sat.credentials.destroy' : null);

  $canGoAccount = Route::has('admin.billing.accounts.show');

  $pageItems = collect($rows ? $rows->items() : []);
  $kpiTotal = (int)($rows?->total() ?? 0);
  $kpiValid = $pageItems->filter(function($row) use ($pick){
    $isValid = (bool) $pick($row, ['is_valid'], false);
    $stRaw   = strtolower(trim((string)$pick($row, ['sat_status'], '')));
    return $isValid || in_array($stRaw, ['validado','valido','ok','active'], true);
  })->count();

  $kpiPending = $pageItems->filter(function($row) use ($pick){
    $isValid = (bool) $pick($row, ['is_valid'], false);
    $isBlock = (bool) $pick($row, ['is_blocked'], false);
    $stRaw   = strtolower(trim((string)$pick($row, ['sat_status'], '')));
    if ($isBlock || in_array($stRaw, ['bloqueado','blocked','error','invalid'], true)) return false;
    if ($isValid || in_array($stRaw, ['validado','valido','ok','active'], true)) return false;
    return true;
  })->count();

  $kpiBlocked = $pageItems->filter(function($row) use ($pick){
    $isBlock = (bool) $pick($row, ['is_blocked'], false);
    $stRaw   = strtolower(trim((string)$pick($row, ['sat_status'], '')));
    return $isBlock || in_array($stRaw, ['bloqueado','blocked','error','invalid'], true);
  })->count();

  $kpiExternal = $pageItems->filter(function($row) use ($pick){
    $extRfc = trim((string)$pick($row, ['external_rfc'], ''));
    $isExt  = (bool)$pick($row, ['from_external'], false);
    return $extRfc !== '' || $isExt;
  })->count();

  $kpiWithFiles = $pageItems->filter(function($row) use ($pick){
    $cer = trim((string)$pick($row, ['cer_path'], ''));
    $key = trim((string)$pick($row, ['key_path'], ''));
    return $cer !== '' && $key !== '';
  })->count();
@endphp

@section('page-header')
  <div class="p360-ph p360-ph-ops">
    <div class="p360-ph-left">
      <div class="p360-ph-kicker">ADMIN · SAT OPS · CREDENCIALES</div>
      <h1 class="p360-ph-title">Credenciales SAT</h1>
      <div class="p360-ph-sub">
        Consola operativa para revisar cuentas, origen, archivos CSD, alertas y validación SAT sin salir del backoffice.
      </div>
    </div>

    <div class="p360-ph-right">
      <a class="p360-btn" href="{{ $backUrl }}">← Volver</a>
      <button type="button" class="p360-btn" onclick="location.reload()">Refrescar</button>
    </div>
  </div>
@endsection

@section('content')

  <div class="ops-wrap" id="p360OpsCred"
       data-csrf="{{ csrf_token() }}"
       data-rt-delete="{{ $rtDeleteName ? route($rtDeleteName, ['id' => '___ID___']) : '' }}">

    {{-- Hero summary --}}
    <section class="ops-hero">
      <div class="ops-hero-main">
        <div class="ops-hero-badge">MÓDULO OPERATIVO</div>
        <h2 class="ops-hero-title">Control centralizado de certificados y accesos SAT</h2>
        <p class="ops-hero-text">
          Consulta rápida por RFC, revisión de origen cliente/externo, descarga de CER/KEY, verificación de alertas y acceso al detalle completo en drawer lateral.
        </p>

        <div class="ops-hero-meta">
          <span class="chip">Ruta <span class="mono">/admin/sat/ops/credentials</span></span>
          <span class="chip">Fuente <span class="mono">mysql_clientes.sat_credentials</span></span>
          <span class="chip">Por página <span class="mono">{{ $per }}</span></span>
        </div>
      </div>

      <div class="ops-hero-side">
        <div class="hero-stat hero-stat-total">
          <div class="hero-stat-k">Registros</div>
          <div class="hero-stat-v">{{ number_format($kpiTotal) }}</div>
          <div class="hero-stat-s">Total del listado filtrado</div>
        </div>

        <div class="hero-stat hero-stat-valid">
          <div class="hero-stat-k">Validados</div>
          <div class="hero-stat-v">{{ number_format($kpiValid) }}</div>
          <div class="hero-stat-s">Página actual</div>
        </div>
      </div>
    </section>

    {{-- KPI cards --}}
    <section class="ops-kpis">
      <article class="ops-kpi-card">
        <div class="ops-kpi-icon ok">✓</div>
        <div class="ops-kpi-copy">
          <div class="ops-kpi-label">Validado</div>
          <div class="ops-kpi-value">{{ number_format($kpiValid) }}</div>
          <div class="ops-kpi-note">Credenciales listas para operar</div>
        </div>
      </article>

      <article class="ops-kpi-card">
        <div class="ops-kpi-icon warn">!</div>
        <div class="ops-kpi-copy">
          <div class="ops-kpi-label">Pendiente</div>
          <div class="ops-kpi-value">{{ number_format($kpiPending) }}</div>
          <div class="ops-kpi-note">Revisar validación o carga</div>
        </div>
      </article>

      <article class="ops-kpi-card">
        <div class="ops-kpi-icon bad">×</div>
        <div class="ops-kpi-copy">
          <div class="ops-kpi-label">Bloqueado</div>
          <div class="ops-kpi-value">{{ number_format($kpiBlocked) }}</div>
          <div class="ops-kpi-note">Con estatus inválido o error</div>
        </div>
      </article>

      <article class="ops-kpi-card">
        <div class="ops-kpi-icon ext">↗</div>
        <div class="ops-kpi-copy">
          <div class="ops-kpi-label">Externo</div>
          <div class="ops-kpi-value">{{ number_format($kpiExternal) }}</div>
          <div class="ops-kpi-note">Entradas de origen externo</div>
        </div>
      </article>

      <article class="ops-kpi-card">
        <div class="ops-kpi-icon files">▣</div>
        <div class="ops-kpi-copy">
          <div class="ops-kpi-label">Con CER / KEY</div>
          <div class="ops-kpi-value">{{ number_format($kpiWithFiles) }}</div>
          <div class="ops-kpi-note">Archivos completos en esta página</div>
        </div>
      </article>
    </section>

    {{-- Filters --}}
    <form class="ops-toolbar" method="GET" action="{{ url()->current() }}">
      <div class="ops-toolbar-top">
        <div class="ops-search">
          <label class="lbl" for="opsSearch">Buscar</label>
          <div class="field-wrap field-wrap-search">
            <span class="field-ico">⌕</span>
            <input id="opsSearch" name="q" class="inp" type="search"
                   value="{{ $q }}"
                   placeholder="RFC, razón social, ID, cuenta_id, account_id, externo..."
                   autocomplete="off">
          </div>
        </div>

        <div class="ops-toolbar-inline">
          <div class="ops-select">
            <label class="lbl" for="opsStatus">Estatus</label>
            <select id="opsStatus" name="status" class="sel">
              <option value=""          @selected($status==='')>Todos</option>
              <option value="validado"  @selected($status==='validado')>Validado</option>
              <option value="pendiente" @selected($status==='pendiente')>Pendiente</option>
              <option value="bloqueado" @selected($status==='bloqueado')>Bloqueado</option>
            </select>
          </div>

          <div class="ops-select">
            <label class="lbl" for="opsOrigin">Origen</label>
            <select id="opsOrigin" name="origin" class="sel">
              <option value=""         @selected($origin==='')>Todos</option>
              <option value="cliente"  @selected($origin==='cliente')>Cliente</option>
              <option value="externo"  @selected($origin==='externo')>Externo</option>
            </select>
          </div>

          <div class="ops-select ops-select-sm">
            <label class="lbl" for="opsPer">Por pág.</label>
            <select id="opsPer" name="per" class="sel">
              @foreach([10,25,50,100,200] as $n)
                <option value="{{ $n }}" @selected((int)$per===$n)>{{ $n }}</option>
              @endforeach
            </select>
          </div>
        </div>
      </div>

      <div class="ops-toolbar-bottom">
        <div class="ops-filter-pills">
          <span class="filter-pill {{ $status === '' ? 'is-active' : '' }}">Estatus: {{ $status !== '' ? ucfirst($status) : 'Todos' }}</span>
          <span class="filter-pill {{ $origin === '' ? 'is-active' : '' }}">Origen: {{ $origin !== '' ? ucfirst($origin) : 'Todos' }}</span>
          <span class="filter-pill">Vista: Operativa</span>
        </div>

        <div class="ops-actions-right">
          <button class="p360-btn p360-btn-primary" type="submit">Aplicar filtros</button>
          <a class="p360-btn" href="{{ url()->current() }}">Limpiar</a>
        </div>
      </div>
    </form>

    {{-- Summary strip --}}
    <div class="ops-summary">
      <div class="sum-left">
        <div class="sum-title">Listado de credenciales</div>
        <div class="sum-sub">
          @if($rows)
            Mostrando {{ $rows->firstItem() ?? 0 }}–{{ $rows->lastItem() ?? 0 }} de {{ $rows->total() }} registros
          @else
            —
          @endif
        </div>
      </div>

      <div class="sum-right">
        <span class="chip">Ctrl/Cmd + K para buscar</span>
        <span class="chip">Click en fila para abrir detalle</span>
      </div>
    </div>

    {{-- List --}}
    <div class="ops-table ops-v5 ops-compact">
      <div class="ops-table-head">
        <div class="th">Cuenta / RFC</div>
        <div class="th">Origen</div>
        <div class="th">Archivos</div>
        <div class="th">SAT / Alertas</div>
        <div class="th">Actividad</div>
        <div class="th th-actions">Acciones</div>
      </div>

      @if(!$rows || $rows->count() === 0)
        <div class="ops-empty">
          <div class="empty-ico">🗂️</div>
          <div class="empty-title">Sin resultados</div>
          <div class="empty-sub">Ajusta los filtros o limpia la búsqueda para volver a cargar registros.</div>
        </div>
      @else
        @foreach($rows as $row)
          @php
            $id       = (string) data_get($row,'id');

            $rfc    = $fmtRfc(data_get($row,'rfc'));
            $name   = $fmt($pick($row, ['razon_social','alias','nombre'], '—'));

            $accountHint = trim((string)$pick($row, ['account_hint'], ''));
            $accountRef  = $fmt($pick($row, ['account_ref_id'], '—'));
            $aidFull     = $fmt($pick($row, ['account_id'], '—'));
            $cidFull     = $fmt($pick($row, ['cuenta_id'], '—'));

            $accountName = trim((string)$pick($row, ['account_name'], ''));
            $accTitle    = $accountName !== '' ? $accountName : ($name !== '—' ? $name : 'Cuenta');

            $accEmail    = trim((string)$pick($row, ['account_email'], ''));
            $accPhone    = trim((string)$pick($row, ['account_phone'], ''));
            $accStatus   = trim((string)$pick($row, ['account_status'], ''));
            $accPlan     = trim((string)$pick($row, ['account_plan'], ''));
            $accCreated  = trim((string)$pick($row, ['account_created_at'], ''));

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

            $hasCer = trim((string)$pick($row, ['cer_path'], '')) !== '';
            $hasKey = trim((string)$pick($row, ['key_path'], '')) !== '';

            $metaRaw = $pick($row, ['meta'], null);
            try{
              if (is_string($metaRaw)) $metaJson = $metaRaw;
              else $metaJson = json_encode($metaRaw, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }catch(\Throwable){
              $metaJson = (string)$metaRaw;
            }
            $metaJson = $metaJson ?: '{}';

            $cerUrl = $rtCerName ? route($rtCerName, ['id' => $id]) : url('/admin/sat/credentials/'.$id.'/cer');
            $keyUrl = $rtKeyName ? route($rtKeyName, ['id' => $id]) : url('/admin/sat/credentials/'.$id.'/key');

            $accountUrl = null;
            $accountLinkId = trim((string)$pick($row, ['account_link_id'], ''));

            if ($accountLinkId === '') {
              $tmp = trim((string)$pick($row, ['account_ref_id'], ''));
              if ($tmp !== '' && ctype_digit($tmp)) $accountLinkId = $tmp;
            }

            if ($canGoAccount && $accountLinkId !== '') {
              try { $accountUrl = route('admin.billing.accounts.show', ['id' => $accountLinkId]); }
              catch(\Throwable) { $accountUrl = null; }
            }
          @endphp

          <div class="tr tr-compact"
               data-row
               data-id="{{ e($id) }}"
               data-rfc="{{ e($rfc) }}"
               data-name="{{ e($name) }}"
               data-acc-title="{{ e($accTitle) }}"
               data-acc-hint="{{ e($accountHint) }}"
               data-acc-ref="{{ e($accountRef) }}"
               data-account-id="{{ e($aidFull) }}"
               data-cuenta-id="{{ e($cidFull) }}"
               data-origin="{{ e($oTxt) }}"
               data-origin-hint="{{ e($originHint) }}"
               data-files="{{ e($fTxt) }}"
               data-has-cer="{{ $hasCer ? '1' : '0' }}"
               data-has-key="{{ $hasKey ? '1' : '0' }}"
               data-has-pass="{{ $hasPass ? '1' : '0' }}"
               data-pass="{{ e($keyPass) }}"
               data-cer-url="{{ e($cerUrl) }}"
               data-key-url="{{ e($keyUrl) }}"
               data-alerts="{{ e($alTxt) }}"
               data-status="{{ e($bTxt) }}"
               data-status-cls="{{ e($bCls) }}"
               data-origin-cls="{{ e($oCls) }}"
               data-files-cls="{{ e($fCls) }}"
               data-alerts-cls="{{ e($alCls) }}"
               data-created="{{ e($fmtDate($createdAt,true)) }}"
               data-updated="{{ e($fmtDate($updatedAt,true)) }}"
               data-validated="{{ e($fmtDate($validatedAt,true)) }}"
               data-last-alert="{{ e($fmtDate($lastAlertAt,true)) }}"
               data-created-ago="{{ e($ago($createdAt)) }}"
               data-updated-ago="{{ e($ago($updatedAt)) }}"
               data-last-alert-ago="{{ e($ago($lastAlertAt)) }}"
               data-account-url="{{ e($accountUrl ?? '') }}"
               data-meta="{{ e($metaJson) }}"
               data-meta-title="{{ e($rfc.' · '.$name) }}"
               data-acc-email="{{ e($accEmail) }}"
               data-acc-phone="{{ e($accPhone) }}"
               data-acc-status="{{ e($accStatus) }}"
               data-acc-plan="{{ e($accPlan) }}"
               data-acc-created="{{ e($accCreated) }}">

            {{-- COL 1 --}}
            <div class="td td-c1">
              <div class="c1-top">
                <div class="c1-avatar">{{ strtoupper(substr($rfc !== '—' ? $rfc : $name, 0, 1)) }}</div>
                <div class="c1-head">
                  <div class="c1-acc" title="{{ $accTitle }}">{{ $accTitle }}</div>
                  <div class="c1-sub">
                    <span class="mono c1-rfc" title="{{ $rfc }}">{{ $rfc }}</span>
                    <span class="dotsep">•</span>
                    <span class="c1-name" title="{{ $name }}">{{ $name }}</span>
                  </div>
                </div>
                <span class="pill {{ $bCls }}">{{ $bTxt }}</span>
              </div>

              <div class="c1-mini">
                <span class="mini mono" title="ID">{{ $short($id, 18) }}</span>
                @if($accountHint !== '')
                  <span class="mini" title="{{ $accountHint }}">Fuente: {{ $accountHint }}</span>
                @endif
              </div>
            </div>

            {{-- COL 2 --}}
            <div class="td td-c2">
              <span class="tag {{ $oCls }}" title="{{ $originHint }}">{{ $oTxt }}</span>
              @if($extRfc !== '')
                <div class="hint mono" title="{{ $extRfc }}">{{ $extRfc }}</div>
              @else
                <div class="hint">Sin RFC externo</div>
              @endif
            </div>

            {{-- COL 3 --}}
            <div class="td td-c3">
              <div class="stack">
                <span class="tag {{ $fCls }}">{{ $fTxt }}</span>
                <div class="files-state">
                  <span class="mini {{ $hasCer ? 'ok' : 'warn' }}">CER</span>
                  <span class="mini {{ $hasKey ? 'ok' : 'warn' }}">KEY</span>
                  <span class="mini {{ $hasPass ? 'ok' : 'warn' }}">PASS</span>
                </div>
              </div>
            </div>

            {{-- COL 4 --}}
            <div class="td td-c4">
              <span class="tag {{ $alCls }}">{{ $alTxt }}</span>
              <div class="hint" title="Última alerta">
                {{ $lastAlertAt ? $ago($lastAlertAt) : 'Sin alertas registradas' }}
              </div>
            </div>

            {{-- COL 5 --}}
            <div class="td td-c5">
              <div class="kvline">
                <span class="k">Alta</span>
                <span class="v" title="{{ $fmtDate($createdAt,true) }}">{{ $ago($createdAt) }}</span>
              </div>
              <div class="kvline">
                <span class="k">Update</span>
                <span class="v" title="{{ $fmtDate($updatedAt,true) }}">{{ $ago($updatedAt) }}</span>
              </div>
            </div>

            {{-- COL 6 --}}
            <div class="td td-actions">
              <div class="act act-compact">
                <button class="a a-primary" type="button" data-open-cred>Ver detalle</button>
                <button class="a a-ghost" type="button" data-copy="{{ e($rfc) }}" data-toast="RFC copiado">Copiar</button>

                <div class="kebab" data-menu>
                  <button class="a a-ghost kebab-btn" type="button" aria-label="Herramientas" data-menu-btn>⋯</button>
                  <div class="kebab-menu" data-menu-panel aria-hidden="true">
                    <button type="button" class="km-item"
                            data-meta-btn
                            data-meta="{{ e($metaJson) }}"
                            data-title="{{ e($rfc.' · '.$name) }}">
                      Ver meta
                    </button>

                    @if($rtDeleteName)
                      <button type="button" class="km-item km-danger"
                              data-delete-id="{{ e($id) }}"
                              data-delete-rfc="{{ e($rfc) }}">
                        Eliminar
                      </button>
                    @endif
                  </div>
                </div>
              </div>
            </div>

          </div>
        @endforeach
      @endif
    </div>

    @if($rows && $rows->hasPages())
      <div class="ops-pager">
        {{ $rows->links() }}
      </div>
    @endif

  </div>

  {{-- Drawer: Detalle credencial --}}
  <div class="p360-drawer p360-drawer-cred" id="credDrawer" aria-hidden="true">
    <div class="drawer-backdrop" data-cred-close></div>

    <div class="drawer-card" role="dialog" aria-modal="true" aria-label="Detalle credencial">
      <div class="drawer-head">
        <div class="drawer-title">
          <div class="d-title-row">
            <span class="mono" id="credRfc">—</span>
            <span class="pill" id="credStatus">—</span>
          </div>
          <div class="d-sub" id="credName">—</div>
        </div>

        <button type="button" class="drawer-x" data-cred-close aria-label="Cerrar">×</button>
      </div>

      <div class="drawer-body">
        <div class="d-grid">

          <section class="d-card">
            <div class="d-card-h">
              <div class="d-card-t">Cuenta</div>
              <a class="d-link" id="credAccountLink" href="#" target="_self" rel="noopener" style="display:none;">Ver cuenta</a>
            </div>

            <div class="d-kv">
              <div class="k">Nombre</div>
              <div class="v" id="credAccTitle">—</div>
            </div>

            <div class="d-kv">
              <div class="k">Fuente</div>
              <div class="v" id="credAccHint">—</div>
            </div>

            <div class="d-kv">
              <div class="k">Estado</div>
              <div class="v" id="credAccStatus">—</div>
            </div>

            <div class="d-kv">
              <div class="k">Plan</div>
              <div class="v" id="credAccPlan">—</div>
            </div>

            <div class="d-kv">
              <div class="k">Email</div>
              <div class="v" id="credAccEmail">—</div>
            </div>

            <div class="d-kv">
              <div class="k">Teléfono</div>
              <div class="v" id="credAccPhone">—</div>
            </div>

            <div class="d-kv">
              <div class="k">Alta</div>
              <div class="v" id="credAccCreated">—</div>
            </div>

            <div class="d-ids">
              <div class="idrow">
                <div class="k">Cuenta ref</div>
                <div class="v mono" id="credAccRef">—</div>
                <button class="a a-mini" type="button" data-copy-from="#credAccRef" data-toast="Cuenta ref copiada">Copiar</button>
              </div>

              <details class="ids-more">
                <summary>IDs avanzados</summary>

                <div class="idrow">
                  <div class="k">account_id</div>
                  <div class="v mono" id="credAccountId">—</div>
                  <button class="a a-mini" type="button" data-copy-from="#credAccountId" data-toast="account_id copiado">Copiar</button>
                </div>

                <div class="idrow">
                  <div class="k">cuenta_id</div>
                  <div class="v mono" id="credCuentaId">—</div>
                  <button class="a a-mini" type="button" data-copy-from="#credCuentaId" data-toast="cuenta_id copiado">Copiar</button>
                </div>
              </details>
            </div>
          </section>

          <section class="d-card">
            <div class="d-card-h">
              <div class="d-card-t">Origen / SAT</div>
              <span class="tag" id="credOriginTag">—</span>
            </div>

            <div class="d-kv">
              <div class="k">Detalle</div>
              <div class="v" id="credOriginHint">—</div>
            </div>

            <div class="d-kv">
              <div class="k">Alertas</div>
              <div class="v">
                <span class="tag" id="credAlertsTag">—</span>
              </div>
            </div>

            <div class="d-kv">
              <div class="k">Última alerta</div>
              <div class="v" id="credLastAlert">—</div>
            </div>
          </section>

          <section class="d-card">
            <div class="d-card-h">
              <div class="d-card-t">Archivos</div>
              <span class="tag" id="credFilesTag">—</span>
            </div>

            <div class="d-actions">
              <a class="a" id="credCerBtn" href="#" target="_self" rel="noopener">Descargar CER</a>
              <a class="a" id="credKeyBtn" href="#" target="_self" rel="noopener">Descargar KEY</a>
            </div>

            <div class="d-pass">
              <div class="k">Password</div>

              <div class="passui" id="credPassUi">
                <input class="passinp" id="credPassInput" type="password" value="" readonly>
                <div class="pass-actions">
                  <button class="a a-ghost" type="button" data-cred-pass-toggle>Ver</button>
                  <button class="a a-ghost" type="button" data-copy-from="#credPassInput" data-toast="Password copiada">Copiar</button>
                </div>
              </div>

              <div class="hint" id="credPassEmpty" style="display:none;">—</div>
            </div>
          </section>

          <section class="d-card">
            <div class="d-card-h">
              <div class="d-card-t">Actividad</div>
              <span class="mini mono" id="credIdShort">—</span>
            </div>

            <div class="d-kv">
              <div class="k">Alta</div>
              <div class="v" id="credCreated">—</div>
            </div>

            <div class="d-kv">
              <div class="k">Actualización</div>
              <div class="v" id="credUpdated">—</div>
            </div>

            <div class="d-kv">
              <div class="k">Validado</div>
              <div class="v" id="credValidated">—</div>
            </div>
          </section>

          <section class="d-card d-card-actions">
            <div class="d-card-h">
              <div class="d-card-t">Acciones</div>
            </div>

            <div class="d-actions d-actions-wide">
              <button class="a" type="button" data-copy-from="#credRfc" data-toast="RFC copiado">Copiar RFC</button>
              <button class="a" type="button" data-copy-from="#credIdFull" data-toast="ID copiado">Copiar ID</button>
              <button class="a a-ghost" type="button" data-open-meta>Ver meta</button>
              <button class="a a-ghost" type="button" data-cred-edit>Editar</button>
              <button class="a a-ghost" type="button" data-cred-refresh>Actualizar</button>

              @if($rtDeleteName)
                <button class="a a-danger" type="button" data-cred-delete>Eliminar</button>
              @endif
            </div>

            <div class="d-hidden">
              <div class="mono" id="credIdFull">—</div>
            </div>
          </section>

        </div>
      </div>

      <div class="drawer-foot">
        <button type="button" class="p360-btn" data-cred-close>Cerrar</button>
      </div>
    </div>
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
  <link rel="stylesheet" href="{{ asset('assets/admin/css/sat-ops-credentials.css') }}?v={{ date('YmdHis') }}">
@endpush

@push('scripts')
  <script src="{{ asset('assets/admin/js/sat-ops-credentials.js') }}?v={{ date('YmdHis') }}" defer></script>
@endpush