{{-- resources/views/admin/sat/ops/credentials/index.blade.php --}}
{{-- P360 · Admin · SAT Ops · Credenciales (v4.0 · Drawer derecho por fila + lista compacta) --}}

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

  // Password: intenta decrypt/base64 y si no, tal cual (último recurso)
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

  // Rutas: preferimos OPS (este controller), si no, fallback a /admin/sat/credentials/...
  $rtCerName = Route::has('admin.sat.ops.credentials.cer') ? 'admin.sat.ops.credentials.cer'
             : (Route::has('admin.sat.credentials.cer') ? 'admin.sat.credentials.cer' : null);

  $rtKeyName = Route::has('admin.sat.ops.credentials.key') ? 'admin.sat.ops.credentials.key'
             : (Route::has('admin.sat.credentials.key') ? 'admin.sat.credentials.key' : null);

  $rtDeleteName = Route::has('admin.sat.ops.credentials.destroy') ? 'admin.sat.ops.credentials.destroy'
               : (Route::has('admin.sat.credentials.destroy') ? 'admin.sat.credentials.destroy' : null);

  $canGoAccount = Route::has('admin.billing.accounts.show');

@endphp

@section('page-header')
  <div class="p360-ph">
    <div class="p360-ph-left">
      <div class="p360-ph-kicker">ADMIN · SAT OPS</div>
      <h1 class="p360-ph-title">Credenciales</h1>
      <div class="p360-ph-sub">Control operativo de RFCs: cuenta, origen, archivos, alertas y estatus SAT.</div>
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

    {{-- Filters --}}
    <form class="ops-toolbar" method="GET" action="{{ url()->current() }}">
      <div class="ops-search">
        <div class="lbl">Buscar</div>
        <input id="opsSearch" name="q" class="inp" type="search"
               value="{{ $q }}" placeholder="Cuenta, RFC, razón social, cuenta_id, ID, externo..." autocomplete="off">
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

    {{-- Summary strip --}}
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
      </div>
    </div>

    {{-- List (compact) --}}
    <div class="ops-table ops-v3 ops-compact">
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
          <div class="empty-sub">Ajusta filtros o limpia la búsqueda.</div>
        </div>
      @else
        @foreach($rows as $row)
          @php
            $id       = (string) data_get($row,'id');

            $rfc    = $fmtRfc(data_get($row,'rfc'));
            $name   = $fmt($pick($row, ['razon_social','alias','nombre'], '—'));

            // Cuenta (detalles reales si vienen del controller)
            $accEmail   = trim((string)$pick($row, ['account_email'], ''));
            $accPhone   = trim((string)$pick($row, ['account_phone'], ''));
            $accStatus  = trim((string)$pick($row, ['account_status'], ''));
            $accPlan    = trim((string)$pick($row, ['account_plan'], ''));
            
            // Cuenta (hidratada por controller)
            $accountHint = trim((string)$pick($row, ['account_hint'], ''));
            $accountRef  = $fmt($pick($row, ['account_ref_id'], '—'));
            $aidFull     = $fmt($pick($row, ['account_id'], '—'));
            $cidFull     = $fmt($pick($row, ['cuenta_id'], '—'));

            $accountName = trim((string)$pick($row, ['account_name'], ''));
            $accTitle    = $accountName !== '' ? $accountName : ($name !== '—' ? $name : 'Cuenta');

            // Detalle real de cuenta (drawer)
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

            // Meta
            $metaRaw = $pick($row, ['meta'], null);
            try{
              if (is_string($metaRaw)) $metaJson = $metaRaw;
              else $metaJson = json_encode($metaRaw, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
            }catch(\Throwable){
              $metaJson = (string)$metaRaw;
            }
            $metaJson = $metaJson ?: '{}';

            // URLs descargas
            $cerUrl = $rtCerName ? route($rtCerName, ['id' => $id]) : url('/admin/sat/credentials/'.$id.'/cer');
            $keyUrl = $rtKeyName ? route($rtKeyName, ['id' => $id]) : url('/admin/sat/credentials/'.$id.'/key');

            // Link cuenta (SIEMPRE debe ser admin_account_id numérico)
            $adminAccountId = trim((string)$pick($row, ['account_admin_id','admin_account_id'], ''));

            // Link cuenta (✅ usa ID numérico real si viene hidratado)
            $accountUrl = null;
            $accountLinkId = trim((string)$pick($row, ['account_link_id'], ''));

            // fallback: si por alguna razón ya viniera numérico en account_ref_id
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
               data-acc-created="{{ e($accCreated) }}"

          >

            {{-- COL 1: Cuenta / RFC --}}
            <div class="td td-c1">
              <div class="c1-top">
                <div class="c1-acc" title="{{ $accTitle }}">{{ $accTitle }}</div>
                <span class="pill {{ $bCls }}">{{ $bTxt }}</span>
              </div>
              <div class="c1-sub">
                <span class="mono c1-rfc" title="{{ $rfc }}">{{ $rfc }}</span>
                <span class="dotsep">•</span>
                <span class="c1-name" title="{{ $name }}">{{ $name }}</span>
              </div>
              <div class="c1-mini">
                <span class="mini mono" title="ID">{{ $short($id, 18) }}</span>
                @if($accountHint !== '')
                  <span class="dotsep">•</span>
                  <span class="mini" title="{{ $accountHint }}">Fuente: {{ $accountHint }}</span>
                @endif
              </div>
            </div>

            {{-- COL 2: Origen --}}
            <div class="td td-c2">
              <span class="tag {{ $oCls }}" title="{{ $originHint }}">{{ $oTxt }}</span>
              @if($extRfc !== '')
                <div class="hint mono" title="{{ $extRfc }}">{{ $extRfc }}</div>
              @endif
            </div>

            {{-- COL 3: Archivos --}}
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

            {{-- COL 4: SAT / Alertas --}}
            <div class="td td-c4">
              <span class="tag {{ $alCls }}">{{ $alTxt }}</span>
              <div class="hint" title="Última alerta">
                {{ $lastAlertAt ? $ago($lastAlertAt) : '—' }}
              </div>
            </div>

            {{-- COL 5: Actividad --}}
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

            {{-- COL 6: Acciones --}}
            <div class="td td-actions">
              <div class="act act-compact">
                <button class="a a-primary" type="button" data-open-cred>Ver</button>
                <button class="a a-ghost" type="button" data-copy="{{ e($rfc) }}" data-toast="RFC copiado">Copiar</button>

                <div class="kebab" data-menu>
                  <button class="kebab-btn" type="button" aria-label="Herramientas" data-menu-btn>⋯</button>
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

    {{-- Pagination --}}
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

          {{-- Cuenta --}}
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

          {{-- Origen / SAT --}}
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

          {{-- Archivos / Password --}}
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

          {{-- Actividad --}}
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

          {{-- Acciones --}}
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

  {{-- Meta Drawer (se conserva) --}}
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
