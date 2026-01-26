{{-- resources/views/admin/sat/ops/credentials/index.blade.php --}}
{{-- P360 · Admin · SAT Ops · Credenciales (v3.6.1 · FIX syntax + UI Cuenta/Password ordenado) --}}

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

  $short = function($v, $n=12){
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

  $canGoAccount = Route::has('admin.accounts.show');
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

    {{-- List --}}
    <div class="ops-table ops-v3">
      <div class="ops-table-head">
        <div class="th">Cuenta</div>
        <div class="th">RFC / Razón social</div>
        <div class="th">Origen</div>
        <div class="th">Archivos</div>
        <div class="th">SAT</div>
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

            // Core: RFC + razon/alias (lo usaremos también para CUENTA si no hay account_name)
            $rfc    = $fmtRfc(data_get($row,'rfc'));
            $name   = $fmt($pick($row, ['razon_social','alias','nombre'], '—'));

            // Account context
            $accountHint = trim((string)$pick($row, ['account_hint'], ''));
            $accountRef  = $fmt($pick($row, ['account_ref_id'], '—'));
            $aidFull     = $fmt($pick($row, ['account_id'], '—'));
            $cidFull     = $fmt($pick($row, ['cuenta_id'], '—'));

            $accountName = trim((string)$pick($row, ['account_name'], ''));
            // “Información real”: account_name si existe, si no usa razón social
            $accTitle = $accountName !== '' ? $accountName : ($name !== '—' ? $name : 'Cuenta');
            $accSub   = $rfc !== '—' ? $rfc : '—';

            $showAccountRef = ($accountRef !== '' && $accountRef !== '—');
            $showAccountId  = ($aidFull    !== '' && $aidFull    !== '—');
            $showCuentaId   = ($cidFull    !== '' && $cidFull    !== '—');

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

            // Link cuenta (si existe y si tenemos referencia “usable”)
            $accountUrl = null;
            if($canGoAccount && $accountRef !== '—' && $accountRef !== ''){
              try { $accountUrl = route('admin.accounts.show', ['account' => $accountRef]); } catch(\Throwable) { $accountUrl = null; }
            }
          @endphp

          <div class="tr" data-row data-id="{{ e($id) }}">

            {{-- CUENTA --}}
            <div class="td td-account-first">
              <div class="acc-top">
                <div class="acc-title" title="{{ $accTitle }}">{{ $accTitle }}</div>
                <div class="acc-sub">
                  <span class="acc-chip mono" title="RFC">{{ $accSub }}</span>

                  @if($accountHint !== '')
                    <span class="acc-chip acc-chip-muted" title="{{ $accountHint }}">Fuente: {{ $accountHint }}</span>
                  @endif

                  @if($showAccountRef)
                    <span class="acc-chip acc-chip-muted mono" title="Account ref">{{ $short($accountRef, 18) }}</span>
                  @endif
                </div>
              </div>

              <div class="acc-ids acc-ids-v3">
                @if($showAccountRef)
                  <div class="acc-row">
                    <div class="acc-k">Account ref</div>
                    <div class="acc-v mono" title="{{ $accountRef }}">{{ $accountRef }}</div>
                    <button class="acc-copy" type="button"
                            data-copy="{{ e($accountRef) }}" data-toast="account_ref copiado">Copiar</button>
                  </div>
                @endif

                @if($showAccountId)
                  <div class="acc-row">
                    <div class="acc-k">Account ID</div>
                    <div class="acc-v mono" title="{{ $aidFull }}">{{ $aidFull }}</div>
                    <button class="acc-copy" type="button"
                            data-copy="{{ e($aidFull) }}" data-toast="account_id copiado">Copiar</button>
                  </div>
                @endif

                @if($showCuentaId)
                  <div class="acc-row">
                    <div class="acc-k">Cuenta ID</div>
                    <div class="acc-v mono" title="{{ $cidFull }}">{{ $cidFull }}</div>
                    <button class="acc-copy" type="button"
                            data-copy="{{ e($cidFull) }}" data-toast="cuenta_id copiado">Copiar</button>
                  </div>
                @endif

                @if(!$showAccountRef && !$showAccountId && !$showCuentaId)
                  <div class="acc-empty">Sin identificadores.</div>
                @endif
              </div>

              @if($accountUrl)
                <a class="link" href="{{ $accountUrl }}">Ver cuenta</a>
              @endif
            </div>

            {{-- RFC / RAZÓN --}}
            <div class="td td-main">
              <div class="main-top">
                <div class="rfc mono" title="{{ $rfc }}">{{ $rfc }}</div>
                <span class="pill {{ $bCls }}">{{ $bTxt }}</span>
              </div>

              <div class="main-name" title="{{ $name }}">{{ $name }}</div>

              <div class="main-sub">
                <span class="mini mono" title="ID completo">{{ $short($id, 22) }}</span>
                @if($validatedAt)
                  <span class="dotsep">•</span>
                  <span class="mini" title="Validado">{{ $fmtDate($validatedAt, true) }}</span>
                @endif
              </div>
            </div>

            {{-- ORIGEN --}}
            <div class="td td-origin">
              <span class="tag {{ $oCls }}" title="{{ $originHint }}">{{ $oTxt }}</span>
              @if($extRfc !== '')
                <div class="hint mono" title="{{ $extRfc }}">{{ $extRfc }}</div>
              @endif
            </div>

            {{-- ARCHIVOS --}}
            <div class="td td-files">
              <div class="files-top">
                <span class="tag {{ $fCls }}">{{ $fTxt }}</span>
                <div class="files-state">
                  <span class="mini {{ $hasCer ? 'ok' : 'warn' }}">CER</span>
                  <span class="mini {{ $hasKey ? 'ok' : 'warn' }}">KEY</span>
                  <span class="mini {{ $hasPass ? 'ok' : 'warn' }}">PASS</span>
                </div>
              </div>

              <div class="files-actions">
                <a class="a {{ $hasCer ? '' : 'is-disabled' }}"
                   href="{{ $hasCer ? $cerUrl : 'javascript:void(0)' }}"
                   @if(!$hasCer) aria-disabled="true" tabindex="-1" @endif
                   title="{{ $hasCer ? 'Descargar CER' : 'No hay CER' }}">
                  Descargar CER
                </a>

                <a class="a {{ $hasKey ? '' : 'is-disabled' }}"
                   href="{{ $hasKey ? $keyUrl : 'javascript:void(0)' }}"
                   @if(!$hasKey) aria-disabled="true" tabindex="-1" @endif
                   title="{{ $hasKey ? 'Descargar KEY' : 'No hay KEY' }}">
                  Descargar KEY
                </a>
              </div>

              {{-- PASSWORD (UI FIX: visible y alineado) --}}
              <div class="passbox">
                <div class="k">Password</div>

                @if($hasPass)
                  <div class="passui">
                    <div class="passmask" title="Password enmascarada" aria-label="Password enmascarada">
                      <span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="dot"></span>
                      <span class="dot"></span><span class="dot"></span><span class="dot"></span><span class="dot"></span>
                    </div>

                    <input class="passinp" type="text" value="{{ $keyPass }}" readonly>

                    <div class="pass-actions">
                      <button class="pbtn" type="button" data-pass-toggle>Ver</button>
                      <button class="pbtn pbtn-ghost" type="button" data-copy="{{ e($keyPass) }}" data-toast="Password copiada">Copiar</button>
                    </div>
                  </div>
                @else
                  <div class="hint">—</div>
                @endif
              </div>
            </div>

            {{-- SAT / ALERTAS --}}
            <div class="td td-status">
              <span class="tag {{ $alCls }}">{{ $alTxt }}</span>
              @if($lastAlertAt)
                <div class="hint" title="Última alerta">{{ $ago($lastAlertAt) }}</div>
              @else
                <div class="hint">—</div>
              @endif
            </div>

            {{-- ACTIVIDAD --}}
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

            {{-- ACCIONES --}}
            <div class="td td-actions">
              <div class="act">
                <button class="a a-ghost" type="button" data-copy="{{ e($rfc) }}" data-toast="RFC copiado">Copiar RFC</button>
                <button class="a a-ghost" type="button" data-copy="{{ e($id) }}" data-toast="ID copiado">Copiar ID</button>

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

  <style>
    /* ---------- Cuenta (más real y ordenado) ---------- */
    .td-account-first .acc-top{display:flex;flex-direction:column;gap:6px;min-width:0}
    .td-account-first .acc-title{
      font-weight:800;font-size:13px;line-height:1.2;color:#0f172a;
      white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:100%;
    }
    .td-account-first .acc-sub{display:flex;gap:6px;flex-wrap:wrap;min-width:0}
    .td-account-first .acc-chip{
      display:inline-flex;align-items:center;gap:6px;
      padding:4px 8px;border-radius:999px;border:1px solid #e2e8f0;background:#f8fafc;
      font-size:11px;font-weight:800;color:#0f172a;max-width:100%;
    }
    .td-account-first .acc-chip-muted{background:#f1f5f9;color:#475569}
    .td-account-first .acc-ids-v3{
      display:flex;flex-direction:column;gap:8px;
      background:#ffffff;border:1px solid #e2e8f0;border-radius:14px;padding:10px;margin-top:8px;
      min-width:0;
    }
    .td-account-first .acc-row{
      display:grid;grid-template-columns: 92px 1fr 74px;gap:10px;align-items:center;
      padding:8px 10px;border:1px solid #eef2f7;border-radius:12px;background:#fbfdff;min-width:0;
    }
    .td-account-first .acc-k{
      font-size:11px;font-weight:800;color:#64748b;text-transform:uppercase;letter-spacing:.06em;white-space:nowrap;
    }
    .td-account-first .acc-v{
      font-size:12px;font-weight:800;color:#0f172a;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;min-width:0;
      font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;
    }
    .td-account-first .acc-copy{
      justify-self:end;height:30px;padding:0 10px;border-radius:999px;border:1px solid #e2e8f0;background:#fff;
      font-weight:800;font-size:12px;cursor:pointer;
    }
    .td-account-first .acc-copy:hover{background:#f8fafc}
    .td-account-first .acc-empty{font-size:12px;color:#64748b;padding:4px 2px}

    /* ---------- Password (que SI se vea) ---------- */
    .td-files .passbox{margin-top:10px}
    .td-files .passbox .k{
      font-size:11px;font-weight:900;color:#64748b;text-transform:uppercase;letter-spacing:.06em;margin-bottom:6px;
    }
    .td-files .passui{
      display:grid;
      grid-template-columns: 1fr auto;
      gap:10px;
      align-items:center;
      padding:10px;
      border:1px solid #e2e8f0;
      border-radius:14px;
      background:#ffffff;
      min-width:0;
    }
    .td-files .passmask{
      display:flex;align-items:center;gap:6px;min-height:34px;
      padding:6px 10px;border:1px dashed #e2e8f0;border-radius:12px;background:#fbfdff;
    }
    .td-files .passmask .dot{
      width:8px;height:8px;border-radius:999px;background:#0f172a;opacity:.55;display:inline-block;
    }
    .td-files .passinp{
      display:none;
      width:100%;
      height:34px;
      padding:0 10px;
      border:1px solid #e2e8f0;
      border-radius:12px;
      font-weight:800;
      font-size:12px;
      color:#0f172a;
      background:#fbfdff;
      font-family:ui-monospace,SFMono-Regular,Menlo,Monaco,Consolas,"Liberation Mono","Courier New",monospace;
      min-width:0;
    }
    .td-files .passui.is-reveal .passmask{display:none}
    .td-files .passui.is-reveal .passinp{display:block}
    .td-files .pass-actions{display:flex;gap:8px;align-items:center}
    .td-files .pbtn{
      height:34px;padding:0 12px;border-radius:999px;border:1px solid #e2e8f0;background:#0f172a;color:#fff;
      font-weight:900;font-size:12px;cursor:pointer;white-space:nowrap;
    }
    .td-files .pbtn:hover{filter:brightness(0.97)}
    .td-files .pbtn-ghost{background:#fff;color:#0f172a}
    .td-files .pbtn-ghost:hover{background:#f8fafc}
  </style>
@endpush

@push('scripts')
  <script src="{{ asset('assets/admin/js/sat-ops-credentials.js') }}?v={{ date('YmdHis') }}" defer></script>

  <script>
  document.addEventListener('click', function(e){
    const tgl = e.target.closest('[data-pass-toggle]');
    if(tgl){
      const box = tgl.closest('.passui');
      if(!box) return;
      const on = box.classList.toggle('is-reveal');
      tgl.textContent = on ? 'Ocultar' : 'Ver';
      return;
    }

    const cpy = e.target.closest('[data-copy]');
    if(cpy){
      const val = cpy.getAttribute('data-copy') || '';
      if(!val) return;
      const toastMsg = cpy.getAttribute('data-toast') || 'Copiado';

      const okToast = () => {
        try {
          if (window.P360 && window.P360.toast && typeof window.P360.toast.success === 'function') {
            return window.P360.toast.success(toastMsg);
          }
        } catch (err) {}
      };

      const fallback = () => {
        try { window.prompt('Copia:', val); } catch(err) {}
      };

      try{
        if(navigator.clipboard && navigator.clipboard.writeText){
          navigator.clipboard.writeText(val).then(okToast).catch(fallback);
          return;
        }
      }catch(err){}
      fallback();
    }
  });
  </script>
@endpush
