{{-- resources/views/cliente/sat/vault.blade.php --}}
@extends('layouts.cliente')
@section('title','Bóveda Fiscal · SAT')

{{-- usar mismo layout ancho que descargas --}}
@section('pageClass','page-sat page-sat-vault')

@php
  use App\Models\Cliente\SatDownload;
  use App\Models\Cliente\SatCredential;

  // ===== Usuario / cuenta =====
  $user   = $user   ?? auth('web')->user();
  $cuenta = $cuenta ?? ($user?->cuenta ?? null);

  // ===== Resumen unificado de cuenta (igual que en index.blade.php) =====
  $summary = $summary ?? app(\App\Http\Controllers\Cliente\HomeController::class)->buildAccountSummary();

  $planFromSummary = strtoupper((string)($summary['plan'] ?? 'FREE'));
  $isProSummary    = (bool)($summary['is_pro'] ?? in_array(
      strtolower($planFromSummary),
      ['pro','premium','empresa','business'],
      true
  ));

  $plan      = $planFromSummary;
  $isProPlan = $isProSummary;
  $isPro     = $isProPlan;

  // ===== RFCs sugeridos para filtros: SOLO RFCs VALIDADOS / ACTIVOS =====
  $credCollection = collect($credList ?? []);

  // Fallback: si no vino $credList, cargamos desde BD por cuenta
  if ($credCollection->isEmpty() && $cuenta) {
      try {
          $cuentaIdForCred = $cuenta->id ?? $cuenta->cuenta_id ?? null;

          if ($cuentaIdForCred) {
              $credCollection = SatCredential::query()
                  ->where('cuenta_id', $cuentaIdForCred)
                  ->orderBy('rfc')
                  ->get();
          }
      } catch (\Throwable $e) {
          \Log::warning('[SAT:vault] No se pudieron cargar credenciales para RFC', [
              'cuenta_id' => $cuenta->id ?? null,
              'error'     => $e->getMessage(),
          ]);
      }
  }

  $rfcOptionsForVault = [];

  foreach ($credCollection as $c) {
      $rfc = strtoupper((string) data_get($c, 'rfc', ''));
      if (!$rfc) continue;

      $nombre = trim((string)(
          data_get($c, 'razon_social') ?:
          data_get($c, 'denominacion') ?:
          data_get($c, 'nombre') ?:
          data_get($c, 'alias') ?:
          ''
      ));

      $estatusRaw = strtolower((string) data_get($c, 'estatus', ''));

      $okFlag = false;

      if (data_get($c, 'validado') ||
          data_get($c, 'validated_at') ||
          data_get($c, 'has_files') ||
          data_get($c, 'has_csd') ||
          data_get($c, 'cer_path') ||
          data_get($c, 'key_path')) {
          $okFlag = true;
      }

      if (in_array($estatusRaw, ['ok','valido','válido','validado','activo','active'], true)) {
          $okFlag = true;
      }

      if (!$okFlag) continue;

      $rfcOptionsForVault[] = ['rfc' => $rfc, 'nombre' => $nombre];
  }

  $rfcs = collect($rfcOptionsForVault)
      ->unique('rfc')
      ->sortBy('rfc')
      ->values()
      ->all();

  // ===== Datos de bóveda / almacenamiento (MISMA cifra que index.blade.php) =====
  $vaultFromCtrl = $vault ?? [];

  // 1) Base incluida por plan
  $vaultBaseGb = 0.0;
  if ($isProPlan) {
      $vaultBaseGb = (float) config('services.sat.vault.base_gb_pro', 0.0);
  }

  // 2) Cuota guardada en cuenta
  $vaultQuotaFromAccount = (float) ($cuenta->vault_quota_gb ?? 0.0);

  // 3) Cuota desde storage si ya fue calculada
  $vaultQuotaFromStorage = (float) ($storage['quota_gb'] ?? (float)($vaultFromCtrl['quota_gb'] ?? 0.0));

  // 4) Uso actual
  $vaultUsedGb = (float) ($storage['used_gb'] ?? ($summary['vault_used_gb'] ?? ($vaultFromCtrl['used_gb'] ?? 0.0)));
  if ($vaultUsedGb < 0) $vaultUsedGb = 0.0;

  // 5) Suma de movimientos VAULT pagados
  $vaultQuotaFromVaultRows = 0.0;

  try {
      $cuentaIdForVault = $cuenta->id ?? $cuenta->cuenta_id ?? null;

      if ($cuentaIdForVault) {
          $vaultPricing = [
              5    => 249.0,
              10   => 449.0,
              20   => 799.0,
              50   => 1499.0,
              100  => 2499.0,
              500  => 7999.0,
              1024 => 12999.0,
          ];

          $vaultRowsPaid = SatDownload::query()
              ->where('cuenta_id', $cuentaIdForVault)
              ->where(function ($q) {
                  $q->where('tipo', 'VAULT')
                    ->orWhere('tipo', 'BOVEDA');
              })
              ->where(function ($q) {
                  $q->whereNotNull('paid_at')
                    ->orWhereIn('status', ['PAID','paid','PAGADO','pagado']);
              })
              ->get();

          $totalGbFromVault = 0.0;

          foreach ($vaultRowsPaid as $vr) {
              $gb = (float) ($vr->vault_gb ?? $vr->gb ?? 0);

              if ($gb <= 0) {
                  $cost = (float) ($vr->costo_mxn ?? $vr->costo ?? 0);
                  if ($cost > 0) {
                      foreach ($vaultPricing as $gbOpt => $priceOpt) {
                          if (abs($priceOpt - $cost) < 0.5) {
                              $gb = (float) $gbOpt;
                              break;
                          }
                      }
                  }
              }

              if ($gb <= 0) {
                  $source = (string) ($vr->alias ?? $vr->nombre ?? '');
                  if (preg_match('/(\d+)\s*gb/i', $source, $m)) {
                      $gb = (float) $m[1];
                  }
              }

              if ($gb > 0) $totalGbFromVault += $gb;
          }

          $vaultQuotaFromVaultRows = $totalGbFromVault;

          \Log::info('[SAT:vault] vault rows paid calc', [
              'cuenta_id'    => $cuentaIdForVault,
              'rows'         => $vaultRowsPaid->count(),
              'gb_from_rows' => $vaultQuotaFromVaultRows,
              'acct_quota'   => $vaultQuotaFromAccount,
              'base_gb'      => $vaultBaseGb,
          ]);
      }
  } catch (\Throwable $e) {
      \Log::warning('[SAT:vault] Error calculando cuota de bóveda desde VAULT', [
          'cuenta_id' => $cuenta->id ?? null,
          'error'     => $e->getMessage(),
      ]);
  }

  // 6) Cuota FINAL (criterio unificado)
  $vaultQuotaGb = max(
      $vaultBaseGb,
      $vaultQuotaFromAccount,
      $vaultQuotaFromVaultRows,
      $vaultQuotaFromStorage
  );

  $vaultPurchasedGb = max(0.0, $vaultQuotaGb - $vaultBaseGb);

  $vaultAvailableGb = $vaultQuotaGb > 0
      ? max(0.0, $vaultQuotaGb - $vaultUsedGb)
      : 0.0;

  $vaultFreeGb  = $vaultAvailableGb;

  $vaultUsedPct = $vaultQuotaGb > 0
      ? min(100, round(($vaultUsedGb / $vaultQuotaGb) * 100))
      : 0;

  $vaultActive = $vaultQuotaGb > 0;

  $vaultForJs = [
      'active'        => $vaultActive,
      'quota_gb'      => $vaultQuotaGb,
      'base_gb'       => $vaultBaseGb,
      'purchased_gb'  => $vaultPurchasedGb,
      'used_gb'       => $vaultUsedGb,
      'available_gb'  => $vaultAvailableGb,
      'used'          => $vaultUsedGb,
      'free'          => $vaultAvailableGb,
      'used_pct'      => $vaultUsedPct,
  ];

  $vaultBtnLabel = $vaultActive ? 'Bóveda activa' : 'Activar bóveda';

  // ===== Boot data para la tabla (SOLO CFDI) =====
  $bootData = $bootData ?? [];
  if ($bootData instanceof \Illuminate\Support\Collection) {
      $bootData = $bootData->all();
  }

  $bootRowsRaw = is_array($bootData) ? $bootData : (array) $bootData;

  $bootRows = collect($bootRowsRaw)->map(function ($row) {
      return is_object($row) ? (array) $row : (array) $row;
  })->filter(function ($row) {
      $kind = strtolower((string)($row['kind'] ?? ''));
      $uuid = trim((string)($row['uuid'] ?? ''));
      return $kind === 'cfdi' && $uuid !== '';
  })->values()->all();

  $bootTotals = [
      'count' => count($bootRows),
      'sub'   => collect($bootRows)->sum('subtotal'),
      'iva'   => collect($bootRows)->sum('iva'),
      'tot'   => collect($bootRows)->sum('total'),
  ];

  // Enriquecer RFCs con los encontrados en CFDI
  try {
      $rfcsFromCfdis = collect($bootRows ?? [])->map(function ($row) {
          if (is_object($row)) $row = (array) $row;

          $rfc = strtoupper((string)(
              $row['rfc'] ?? $row['rfc_emisor'] ?? $row['rfc_receptor']
          ));

          if (!$rfc) return null;

          $nombre = trim((string)(
              $row['razon'] ?? $row['razon_social'] ??
              $row['razon_emisor'] ?? $row['razon_receptor'] ?? ''
          ));

          return ['rfc' => $rfc, 'nombre' => $nombre];
      })->filter()->values()->all();

      $rfcs = collect($rfcs ?? [])
          ->merge($rfcsFromCfdis)
          ->unique('rfc')
          ->sortBy('rfc')
          ->values()
          ->all();
  } catch (\Throwable $e) {
      \Log::warning('[SAT:vault] No se pudieron derivar RFCs desde CFDI de bóveda', [
          'cuenta_id' => $cuenta->id ?? null,
          'error'     => $e->getMessage(),
      ]);
  }

  // ===== Rutas de carrito SAT =====
  $rtCartIndex  = \Route::has('cliente.sat.cart.index')  ? route('cliente.sat.cart.index')  : null;
  $rtCartList   = \Route::has('cliente.sat.cart.list')   ? route('cliente.sat.cart.list')   : $rtCartIndex;
  $rtCartAdd    = \Route::has('cliente.sat.cart.add')    ? route('cliente.sat.cart.add')    : null;
  $rtCartRemove = \Route::has('cliente.sat.cart.remove') ? route('cliente.sat.cart.remove') : null;
  $rtCartPay    = \Route::has('cliente.sat.cart.checkout') ? route('cliente.sat.cart.checkout') : null;

  // ===== Rutas específicas de bóveda =====
  $rtVaultExport = \Route::has('cliente.sat.vault.export') ? route('cliente.sat.vault.export') : null;
  $rtVaultXml    = \Route::has('cliente.sat.vault.xml')    ? route('cliente.sat.vault.xml')    : null;
  $rtVaultPdf    = \Route::has('cliente.sat.vault.pdf')    ? route('cliente.sat.vault.pdf')    : null;
  $rtVaultZip    = \Route::has('cliente.sat.vault.zip')    ? route('cliente.sat.vault.zip')    : null;

  // ZIPs guardados (tabla secundaria)
  $vaultZipRows = $vaultZipRows ?? [];
@endphp

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/sat-dashboard.css') }}">
  <link rel="stylesheet" href="{{ asset('assets/client/css/sat-vault.css') }}">
@endpush

@section('content')
<div class="sat-ui" id="satVaultApp">
  <div class="vault-page">

    {{-- ================= HEADER ================= --}}
    <div class="sat-card vault-header-card">
      <div class="vault-header-left">
        <div class="vault-title-icon">
          <svg viewBox="0 0 24 24" class="vault-ico" stroke="currentColor" fill="none">
            <rect x="3" y="4" width="18" height="16" rx="2"></rect>
            <path d="M3 9h18"></path>
          </svg>
        </div>
        <div>
          <div class="vault-title-main">Bóveda Fiscal</div>
          <div class="vault-title-sub">
            Consolida tus CFDI para análisis, reportes y descargas contables.
          </div>
        </div>
      </div>

      <div class="vault-header-right">
        <div class="vault-summary">
          <div class="vault-summary-label">Capacidad de bóveda</div>

          @if($vaultActive)
            <div class="vault-summary-pill">
              <span class="vault-summary-used">{{ number_format($vaultUsedGb, 2) }} Gb usados</span>
              <span class="vault-summary-dot">·</span>
              <span class="vault-summary-free">{{ number_format($vaultFreeGb, 2) }} Gb libres</span>
              <span class="vault-summary-dot">·</span>
              <span class="vault-summary-free">Cuota {{ number_format($vaultQuotaGb, 2) }} Gb</span>
            </div>

            <div class="vault-summary-bar">
              <div class="vault-summary-bar-bg">
                <div class="vault-summary-bar-fill" style="width: {{ $vaultUsedPct }}%;"></div>
              </div>
              <div class="vault-summary-bar-info">{{ $vaultUsedPct }}% de tu bóveda en uso</div>
            </div>
          @else
            <div class="vault-summary-pill vault-summary-pill--off">
              <span>Bóveda desactivada · 0.00 Gb usados</span>
            </div>
            <div class="vault-summary-bar-info vault-summary-bar-info--hint">
              Activa tu bóveda para centralizar tus CFDI históricos.
            </div>
          @endif
        </div>

        <div class="vault-cart-wrap">
          <div class="sat-cart-compact" id="satCartWidget">
            <span class="sat-cart-label">Carrito SAT</span>
            <span class="sat-cart-pill">
              <span id="satCartCount">0</span> ítems ·
              <span id="satCartTotal">$0.00</span>
              · <span id="satCartWeight">0.00 MB</span>
            </span>
          </div>

          <button
            type="button"
            class="btn btn-amazon{{ $vaultActive ? ' btn-amazon--active' : '' }}"
            id="btnVaultCta"
            @if($vaultActive) disabled aria-disabled="true" @endif
          >
            <span class="btn-amazon-icon" aria-hidden="true">💾</span>
            <span class="btn-amazon-label">{{ $vaultBtnLabel }}</span>
          </button>
        </div>
      </div>
    </div>

    {{-- ================= RESUMEN RÁPIDO CFDI ================= --}}
    <div class="sat-card vault-quick-card">
      <div class="vqs-header">
        <div>
          <h2 class="vqs-title">Resumen rápido CFDI</h2>
          <p class="vqs-subtitle">Panorama general de tus CFDI descargados y almacenados en la bóveda.</p>
        </div>
        <div class="vqs-period-chip">Últimos 6 meses</div>
      </div>

      <div class="vqs-main">
        <div class="vqs-kpi-grid">
          <article class="vqs-kpi">
            <div class="vqs-kpi-label">Total CFDI</div>
            <div class="vqs-kpi-main">
              <span id="vqCount" class="vqs-kpi-number">0</span>
              <span class="vqs-kpi-tag">documentos</span>
            </div>
            <div class="vqs-kpi-footer">
              Monto total: <strong id="vqTotal" class="vqs-kpi-money">$0.00</strong>
            </div>
          </article>

          <article class="vqs-kpi">
            <div class="vqs-kpi-label">
              Emitidos <span class="vqs-dot vqs-dot--em"></span>
            </div>
            <div class="vqs-kpi-main">
              <span id="vqEmitCount" class="vqs-kpi-number">0</span>
              <span class="vqs-kpi-tag">CFDI</span>
            </div>
            <div class="vqs-kpi-footer">
              Total: <strong id="vqEmitTotal" class="vqs-kpi-money">$0.00</strong>
            </div>
          </article>

          <article class="vqs-kpi">
            <div class="vqs-kpi-label">
              Recibidos <span class="vqs-dot vqs-dot--rec"></span>
            </div>
            <div class="vqs-kpi-main">
              <span id="vqRecCount" class="vqs-kpi-number">0</span>
              <span class="vqs-kpi-tag">CFDI</span>
            </div>
            <div class="vqs-kpi-footer">
              Total: <strong id="vqRecTotal" class="vqs-kpi-money">$0.00</strong>
            </div>
          </article>

          <article class="vqs-kpi">
            <div class="vqs-kpi-label">IVA estimado</div>
            <div class="vqs-kpi-main">
              <span id="vqIvaTotal" class="vqs-kpi-number">$0.00</span>
            </div>
            <div class="vqs-kpi-footer">
              Subtotal: <strong id="vqSubTotal" class="vqs-kpi-money">$0.00</strong>
            </div>
          </article>
        </div>

        <div class="vqs-top-card">
          <div class="vqs-top-header">
            <div>
              <div class="vqs-top-title">Top 5 RFC / Razón social por monto</div>
              <div class="vqs-top-subtitle">Contribuyentes con mayor importe acumulado en el periodo.</div>
            </div>
          </div>

          <div class="vq-table-wrapper">
            <table class="vq-table">
              <thead>
                <tr>
                  <th>RFC</th>
                  <th>Razón social CFDI</th>
                  <th class="t-center">CFDI</th>
                  <th class="t-right">Total</th>
                </tr>
              </thead>
              <tbody id="vqTopRfcBody">
                <tr>
                  <td colspan="4" class="vq-empty">Sin datos suficientes todavía…</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>

      </div>
    </div>

    {{-- ================= MÉTRICAS ================= --}}
    <div class="sat-card vault-metrics-card">
      <div class="vm-col">
        <div>
          <div class="vm-label">CFDI filtrados</div>
          <div class="vm-main" id="vmTotalCount">0</div>
          <div class="vm-sub">
            Emitidos: <b id="vmTotalEmitidos">0</b> ·
            Recibidos: <b id="vmTotalRecibidos">0</b>
          </div>
        </div>

        <div class="vm-bar-wrapper">
          <div class="vm-bar-bg">
            <div class="vm-bar-fill vm-bar-emitidos" id="vmBarEmitidos"></div>
          </div>
          <div class="vm-bar-bg">
            <div class="vm-bar-fill vm-bar-recibidos" id="vmBarRecibidos"></div>
          </div>
          <div class="vm-sub">
            <span class="vm-tag"><span class="vm-tag-dot"></span> Emitidos</span>
            <span class="vm-tag"><span class="vm-tag-dot vm-tag-dot--blue"></span> Recibidos</span>
          </div>
        </div>
      </div>

      <div class="vm-col">
        <div>
          <div class="vm-label">Total filtrado (MXN)</div>
          <div class="vm-main" id="vmTotGlobal">$0.00</div>
          <div class="vm-sub">
            Emitidos: <b id="vmTotEmitidos">$0.00</b> ·
            Recibidos: <b id="vmTotRecibidos">$0.00</b>
          </div>
        </div>
      </div>

      <div class="vm-col">
        <div>
          <div class="vm-label">Ticket promedio / RFC top</div>
          <div class="vm-main" id="vmAvgTotal">$0.00</div>
          <div class="vm-sub">
            RFC con mayor monto: <b id="vmTopRfc">—</b><br>
            Monto acumulado: <b id="vmTopRfcTot">$0.00</b>
          </div>
        </div>
      </div>
    </div>

    {{-- ================= GRÁFICAS ================= --}}
    <div class="sat-card vault-charts-card">
      <div class="vault-chart-box">
        <div class="vault-chart-header">
          <div>
            <div class="vault-chart-title">Distribución por tipo</div>
            <div class="vault-chart-caption">CFDI emitidos vs recibidos</div>
          </div>
        </div>

        <div class="vault-chart-canvas-wrap">
          <canvas id="vaultChartTipo"></canvas>
        </div>

        <div class="vault-chart-footer">
          <div class="vault-chart-legend">
            <span class="legend-item"><span class="legend-dot legend-dot--em"></span><span>Emitidos</span></span>
            <span class="legend-item"><span class="legend-dot legend-dot--rec"></span><span>Recibidos</span></span>
          </div>
          <div class="vault-chart-hint">Pasa el cursor sobre el gráfico para ver el detalle.</div>
        </div>
      </div>

      <div class="vault-chart-box">
        <div class="vault-chart-header">
          <div>
            <div class="vault-chart-title">Top RFC por monto</div>
            <div class="vault-chart-caption">Top 5 por total acumulado</div>
          </div>
        </div>

        <div class="vault-chart-canvas-wrap">
          <canvas id="vaultChartTopRfc"></canvas>
        </div>

        <div class="vault-chart-footer">
          <div class="vault-chart-hint">Las etiquetas de cada barra aparecen al pasar el cursor.</div>
        </div>
      </div>

      <div class="vault-chart-box">
        <div class="vault-chart-header">
          <div>
            <div class="vault-chart-title">Flujo diario</div>
            <div class="vault-chart-caption">Total por fecha</div>
          </div>
        </div>

        <div class="vault-chart-canvas-wrap">
          <canvas id="vaultChartFlujo"></canvas>
        </div>

        <div class="vault-chart-footer">
          <div class="vault-chart-hint">Escala automática; usa el tooltip para ver el monto exacto.</div>
        </div>
      </div>
    </div>

    {{-- ================= FILTROS ================= --}}
    <div class="sat-card vault-filters-card">
      <div class="vault-filters-grid">
        <div class="vf-field">
          <label class="vf-label">Tipo de CFDI</label>
          <select class="select" id="fTipo" aria-label="Tipo CFDI">
            <option value="ambos">Ambos</option>
            <option value="emitidos">Emitidos</option>
            <option value="recibidos">Recibidos</option>
          </select>
        </div>

        <div class="vf-field">
          <label class="vf-label">RFC</label>
          <select class="select" id="fRfc" aria-label="RFC">
            <option value="">Todos los RFC</option>
            @foreach($rfcs as $opt)
              @php
                $rfc    = is_array($opt) ? ($opt['rfc'] ?? '') : (string) $opt;
                $nombre = is_array($opt) ? ($opt['nombre'] ?? '') : '';
              @endphp
              @if($rfc)
                <option value="{{ $rfc }}">
                  {{ $rfc }}@if($nombre) — {{ \Illuminate\Support\Str::limit($nombre, 40) }}@endif
                </option>
              @endif
            @endforeach
          </select>
        </div>

        <div class="vf-field vf-field--span2">
          <label class="vf-label">Búsqueda libre</label>
          <input type="text" class="input" id="fQuery" placeholder="RFC, razón social o UUID" aria-label="Búsqueda libre">
        </div>

        <div class="vf-field">
          <label class="vf-label">Desde</label>
          <input type="date" class="input" id="fDesde" aria-label="Desde">
        </div>

        <div class="vf-field">
          <label class="vf-label">Hasta</label>
          <input type="date" class="input" id="fHasta" aria-label="Hasta">
        </div>

        <div class="vf-field">
          <label class="vf-label">Mínimo (MXN)</label>
          <input type="number" class="input" id="fMin" placeholder="0.00" step="0.01" min="0" aria-label="Mínimo">
        </div>

        <div class="vf-field">
          <label class="vf-label">Máximo (MXN)</label>
          <input type="number" class="input" id="fMax" placeholder="0.00" step="0.01" min="0" aria-label="Máximo">
        </div>

        <div class="vf-field vf-field--chips">
          <span class="vf-label vf-label--inline">Rangos rápidos</span>
          <div class="vault-filters-chips">
            <button type="button" class="btn soft btn-chip vault-quick-date" data-range="today">Hoy</button>
            <button type="button" class="btn soft btn-chip vault-quick-date" data-range="week">Esta semana</button>
            <button type="button" class="btn soft btn-chip vault-quick-date" data-range="month">Este mes</button>
            <button type="button" class="btn soft btn-chip vault-quick-date" data-range="year">Año actual</button>
            <button type="button" class="btn soft btn-chip vault-quick-date" data-range="all">Todo</button>
          </div>
        </div>

        <div class="vf-actions">
          <button type="button" class="btn soft" id="btnApply"><span aria-hidden="true">🔍</span><span>Filtrar</span></button>
          <button type="button" class="btn soft" id="btnClear"><span aria-hidden="true">🧹</span><span>Limpiar</span></button>
        </div>
      </div>
    </div>

    {{-- ================= TOTALES + TABLA CFDI ================= --}}
    <div class="sat-card vault-table-card">
      <div class="vault-table-summary">
        <div class="vts-box vts-box--count">
          <span class="vts-label">CFDI filtrados</span>
          <span class="vts-value vts-value-count" id="tCnt">0</span>
        </div>

        <div class="vts-box">
          <span class="vts-label">Subtotal</span>
          <span class="vts-value" id="tSub">$0.00</span>
        </div>

        <div class="vts-box">
          <span class="vts-label">IVA</span>
          <span class="vts-value" id="tIva">$0.00</span>
        </div>

        <div class="vts-box">
          <span class="vts-label">Total</span>
          <span class="vts-value vts-value-strong" id="tTot">$0.00</span>
        </div>

        <div class="vts-box vts-box--action">
          <button type="button" class="btn btn-primary btn-report vts-export-btn" id="btnExportVault">
            <span aria-hidden="true" class="vts-export-icon">📊</span>
            <span class="vts-export-label">Exportar Excel</span>
          </button>
          <span class="vts-hint">Se exportan los CFDI según los filtros actuales.</span>
        </div>
      </div>

      <div class="vault-table-shell">
        <div class="vault-table-top">
          <div class="vault-table-title">
            <div class="vault-table-title-main">CFDI en bóveda</div>
            <div class="vault-table-title-sub">
              Haz clic en XML/PDF/ZIP para descargar por UUID. Usa filtros para acotar resultados.
            </div>
          </div>

          <div class="vault-table-tools">
            <div class="vault-table-chip">
              <span class="vault-table-chip-label">Mostrando</span>
              <span class="vault-table-chip-value" id="pgInfo">0–0 de 0</span>
            </div>

            <div class="vault-table-size">
              <span class="vault-table-size-label">Por página</span>
              <select class="select" id="pgSize" aria-label="Resultados por página">
                <option>10</option>
                <option selected>25</option>
                <option>50</option>
                <option>100</option>
              </select>
            </div>
          </div>
        </div>

        <div class="vault-table-scroll" role="region" aria-label="Tabla CFDI" tabindex="0">
          <table class="vault-table-pro" id="vaultTable" aria-label="CFDI almacenados">
            <thead>
              <tr>
                <th class="th-date">Fecha</th>
                <th class="th-tipo">Tipo</th>
                <th class="th-rfc">RFC</th>
                <th class="th-razon">Razón social</th>
                <th class="th-uuid">UUID</th>
                <th class="th-money t-right">Subtotal</th>
                <th class="th-money t-right">IVA</th>
                <th class="th-money t-right">% IVA</th>
                <th class="th-money t-right">Total</th>
                <th class="th-actions t-center">Acciones</th>
              </tr>
            </thead>
            <tbody id="vaultRows">
              <tr><td colspan="10" class="empty-cell">Sin datos</td></tr>
            </tbody>
          </table>
        </div>

        <div class="vault-table-bottom">
          <div class="vault-pag-left">
            <button class="btn soft" id="pgPrev">← Anterior</button>
            <button class="btn soft" id="pgNext">Siguiente →</button>
          </div>
          <div class="vault-pag-right">
            <span class="vault-page-info mono" id="pgInfoMirror">0–0 de 0</span>
          </div>
        </div>
      </div>
    </div>

    {{-- ================= ZIPs GUARDADOS EN BÓVEDA ================= --}}
    <div class="sat-card vault-table-card" style="margin-bottom:16px;">
      <div class="vault-table-top">
        <div class="vault-table-title">
          <div class="vault-table-title-main">Archivos ZIP guardados en bóveda</div>
          <div class="vault-table-title-sub">
            Estos son los paquetes ZIP que ya guardaste. (La tabla principal solo muestra CFDI con UUID.)
          </div>
        </div>
      </div>

      <div class="vault-table-shell">
        <div class="vault-table-scroll" role="region" aria-label="Tabla ZIP" tabindex="0">
          <table class="vault-table-pro" aria-label="ZIPs en bóveda">
            <thead>
              <tr>
                <th class="th-date">Fecha</th>
                <th class="th-rfc">RFC</th>
                <th class="th-razon">Archivo</th>
                <th class="th-money t-right">Tamaño</th>
                <th class="th-actions t-center">Acción</th>
              </tr>
            </thead>
            <tbody>
              @if(empty($vaultZipRows))
                <tr><td colspan="5" class="empty-cell">Aún no hay ZIPs guardados en bóveda.</td></tr>
              @else
                @foreach($vaultZipRows as $z)
                  <tr>
                    <td>{{ $z['fecha'] ?? '—' }}</td>
                    <td>{{ $z['rfc'] ?? '—' }}</td>
                    <td>{{ $z['filename'] ?? '—' }}</td>
                    <td class="t-right">{{ number_format(((int)($z['bytes'] ?? 0))/1024, 2) }} KB</td>
                    <td class="t-center">
                      @if(\Route::has('cliente.sat.vault.file'))
                        <a class="btn soft" href="{{ route('cliente.sat.vault.file', ['id' => $z['id']]) }}">Descargar ZIP</a>
                      @else
                        <span class="mono">Ruta no configurada</span>
                      @endif
                    </td>
                  </tr>
                @endforeach
              @endif
            </tbody>
          </table>
        </div>
      </div>
    </div>

    {{-- ================= MODAL ACTIVAR BÓVEDA ================= --}}
    <div id="vaultConfirm" class="vault-modal" aria-hidden="true">
      <div class="vault-modal__backdrop"></div>

      <div class="vault-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="vaultConfirmTitle">
        <div class="vault-modal__icon"><span>🗄️</span></div>

        <h2 id="vaultConfirmTitle" class="vault-modal__title">Activar bóveda fiscal</h2>

        <p class="vault-modal__lead">Vas a activar tu bóveda fiscal usando el carrito SAT.</p>

        <p class="vault-modal__text">
          Se agregará un concepto de <strong>bóveda fiscal</strong> a tu carrito para que lo puedas pagar junto con tus descargas.
        </p>

        <div class="vault-modal__note">
          <span class="dot"></span>
          <span>Podrás revisar el detalle del cargo antes de confirmar el pago.</span>
        </div>

        <div class="vault-modal__actions">
          <button type="button" class="btn btn-secondary" data-vault-dismiss>Cancelar</button>
          <button type="button" class="btn btn-primary" data-vault-accept>Sí, agregar al carrito</button>
        </div>
      </div>
    </div>

  </div>
</div>
@endsection

@push('scripts')
  {{-- Chart.js para las gráficas de la bóveda --}}
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.4/dist/chart.umd.min.js"></script>

  {{-- Boot inicial de CFDI para la tabla --}}
  <script>
    window.__VAULT_BOOT = @json([
      'rows'   => $bootRows,
      'totals' => $bootTotals,
    ]);
  </script>

  {{-- Config global SAT (carrito + rutas de bóveda) --}}
  <script>
    window.P360_SAT = {
      csrf: '{{ csrf_token() }}',
      isProPlan: @json($isPro ?? false),
      downloads: [],
      routes: {
        cartIndex:    @json($rtCartIndex),
        cartList:     @json($rtCartList),
        cartAdd:      @json($rtCartAdd),
        cartRemove:   @json($rtCartRemove),
        cartCheckout: @json($rtCartPay),
        cartPay:      @json($rtCartPay),

        vaultIndex:   @json(\Route::has('cliente.sat.vault') ? route('cliente.sat.vault') : null),
        vaultFromDownload: @json(
          \Route::has('cliente.sat.vault.fromDownload')
            ? route('cliente.sat.vault.fromDownload', ['download' => '__ID__'])
            : null
        ),

        vaultExport:  @json($rtVaultExport),
        vaultXml:     @json($rtVaultXml),
        vaultPdf:     @json($rtVaultPdf),
        vaultZip:     @json($rtVaultZip),
      },
      vault: @json($vaultForJs),
    };
  </script>

  <script>
  document.addEventListener('DOMContentLoaded', function () {
    const boot = window.__VAULT_BOOT || { rows: [], totals: {} };

    const allRowsRaw = Array.isArray(boot.rows) ? boot.rows : [];
    const allRows = allRowsRaw.filter(r => {
      const kind = String((r && r.kind) || '').toLowerCase();
      const uuid = String((r && r.uuid) || '').trim();
      return kind === 'cfdi' && uuid !== '';
    });

    const ROUTES = (window.P360_SAT && window.P360_SAT.routes) || {};

    const tbody  = document.getElementById('vaultRows');
    if (!tbody) return;

    const tCnt   = document.getElementById('tCnt');
    const tSub   = document.getElementById('tSub');
    const tIva   = document.getElementById('tIva');
    const tTot   = document.getElementById('tTot');

    const pgInfo = document.getElementById('pgInfo');
    const pgPrev = document.getElementById('pgPrev');
    const pgNext = document.getElementById('pgNext');
    const pgSize = document.getElementById('pgSize');

    const fTipo  = document.getElementById('fTipo');
    const fDesde = document.getElementById('fDesde');
    const fHasta = document.getElementById('fHasta');
    const fRfc   = document.getElementById('fRfc');
    const fQuery = document.getElementById('fQuery');
    const fMin   = document.getElementById('fMin');
    const fMax   = document.getElementById('fMax');

    const btnApply      = document.getElementById('btnApply');
    const btnClear      = document.getElementById('btnClear');
    const btnExport     = document.getElementById('btnExportVault');
    const quickDateBtns = document.querySelectorAll('.vault-quick-date');

    const vmTotalCount     = document.getElementById('vmTotalCount');
    const vmTotalEmitidos  = document.getElementById('vmTotalEmitidos');
    const vmTotalRecibidos = document.getElementById('vmTotalRecibidos');
    const vmAvgTotal       = document.getElementById('vmAvgTotal');
    const vmTopRfc         = document.getElementById('vmTopRfc');
    const vmTopRfcTot      = document.getElementById('vmTopRfcTot');
    const vmBarEmitidos    = document.getElementById('vmBarEmitidos');
    const vmBarRecibidos   = document.getElementById('vmBarRecibidos');

    const vmTotGlobal      = document.getElementById('vmTotGlobal');
    const vmTotEmitidos    = document.getElementById('vmTotEmitidos');
    const vmTotRecibidos   = document.getElementById('vmTotRecibidos');

    const vqTopBody        = document.getElementById('vqTopRfcBody');

    function fmtMoney(n) {
      const num = Number(n || 0);
      return num.toLocaleString('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      });
    }

    function fmtPct(p) {
      const num = Number(p || 0);
      return num.toFixed(2) + '%';
    }

    function setText(id, value) {
      const el = document.getElementById(id);
      if (el) el.textContent = value;
    }

    function parseDate(str) {
      if (!str) return null;
      const s = String(str).slice(0, 10);
      const t = Date.parse(s);
      return isNaN(t) ? null : new Date(t);
    }

    function getRowDate(row) {
      const cands = [row.fecha, row.fecha_emision, row.fecha_cfdi, row.fecha_timbrado, row.created_at];
      for (const v of cands) {
        const d = parseDate(v);
        if (d) return d;
      }
      return null;
    }

    function toNum(v) {
      if (v == null) return 0;
      if (typeof v === 'number') return Number.isFinite(v) ? v : 0;
      const s = String(v).trim();
      if (s === '') return 0;
      const cleaned = s.replace(/[^0-9.\-]/g, '');
      const n = Number(cleaned);
      return Number.isFinite(n) ? n : 0;
    }

    function getRowTotal(row) {
      const disp = row.__display || {};
      if (disp.total != null) return toNum(disp.total);

      const fields = ['total','total_mxn','total_cfdi','importe','importe_mxn','monto','monto_total'];
      for (const f of fields) {
        if (row[f] != null && row[f] !== '') return toNum(row[f]);
      }
      return 0;
    }

    function getRfcAndRazon(row) {
      const rfc =
        row.rfc || row.rfc_emisor || row.rfc_receptor ||
        row.emisor_rfc || row.receptor_rfc || '';

      const razon =
        row.razon || row.razon_social || row.razon_emisor || row.razon_receptor ||
        row.nombre_emisor || row.nombre_receptor || '';

      return { rfc: String(rfc || '').toUpperCase(), razon: String(razon || '') };
    }

    function formatDateInput(d) {
      if (!(d instanceof Date)) return '';
      const y = d.getFullYear();
      const m = String(d.getMonth() + 1).padStart(2, '0');
      const day = String(d.getDate()).padStart(2, '0');
      return `${y}-${m}-${day}`;
    }

    function tdText(text, extraClass) {
      const td = document.createElement('td');
      if (extraClass) td.className = extraClass;
      td.textContent = text;
      return td;
    }

    function tdMoney(amount) {
      const td = document.createElement('td');
      td.className = 't-right';
      const span = document.createElement('span');
      span.className = 'vault-amount';
      span.textContent = fmtMoney(amount);
      td.appendChild(span);
      return td;
    }

    function tdPctCell(pct) {
      const td = document.createElement('td');
      td.className = 't-right';
      const span = document.createElement('span');
      span.className = 'vault-amount';
      span.textContent = fmtPct(pct);
      td.appendChild(span);
      return td;
    }

    const state = {
      page: 1,
      pageSize: Number(pgSize ? pgSize.value : 25) || 25,
      filtered: [],
      totals: { count: 0, sub: 0, iva: 0, tot: 0 },
    };

    function applyFilters() {
      const tipoVal = (fTipo && fTipo.value) ? fTipo.value.toLowerCase() : 'ambos';
      const rfcVal  = (fRfc && fRfc.value || '').trim().toUpperCase();
      const qVal    = (fQuery && fQuery.value || '').trim().toUpperCase();

      const minVal  = (fMin && fMin.value !== '') ? Number(fMin.value) : null;
      const maxVal  = (fMax && fMax.value !== '') ? Number(fMax.value) : null;

      const dDesde  = (fDesde && fDesde.value) ? new Date(fDesde.value) : null;
      const dHasta  = (fHasta && fHasta.value) ? new Date(fHasta.value) : null;
      if (dHasta) dHasta.setDate(dHasta.getDate() + 1);

      let count = 0, sumSub = 0, sumIva = 0, sumTot = 0;
      const out = [];

      for (const r of allRows) {
        const kind = String(r.kind || '').toLowerCase();
        const uuid = String(r.uuid || '').trim();
        if (kind !== 'cfdi' || !uuid) continue;

        const tipoRow = String(r.tipo || '').toLowerCase();
        if (tipoVal !== 'ambos' && tipoRow !== tipoVal) continue;

        const fRow = getRowDate(r);
        if (dDesde && (!fRow || fRow < dDesde)) continue;
        if (dHasta && (!fRow || fRow >= dHasta)) continue;

        const info  = getRfcAndRazon(r);
        const rfc   = info.rfc;
        const razon = info.razon;

        if (rfcVal && rfc !== rfcVal) continue;

        if (qVal) {
          const hay = (rfc + ' ' + razon + ' ' + uuid).toUpperCase();
          if (!hay.includes(qVal)) continue;
        }

        let subtotal = toNum(r.subtotal ?? r.subtotal_mxn ?? 0);
        let iva      = toNum(r.iva ?? r.iva_mxn ?? 0);
        let total    = toNum(r.total ?? r.total_mxn ?? r.total_cfdi ?? r.importe ?? r.monto_total ?? 0);

        if (!Number.isFinite(subtotal) || subtotal < 0) subtotal = 0;
        if (!Number.isFinite(iva)      || iva < 0)      iva = 0;
        if (!Number.isFinite(total)    || total < 0)    total = 0;

        if (total <= 0 && (subtotal > 0 || iva > 0)) total = subtotal + iva;
        if (iva <= 0 && subtotal > 0 && total > subtotal) {
          const diff = total - subtotal;
          iva = diff > 0.00001 ? diff : 0;
        }
        if (subtotal <= 0 && total > 0 && iva > 0 && total >= iva) {
          const diff = total - iva;
          subtotal = diff > 0.00001 ? diff : 0;
        }

        let ivaPct = 0;
        if (subtotal > 0 && iva > 0) ivaPct = (iva / subtotal) * 100;

        if (minVal !== null && total < minVal) continue;
        if (maxVal !== null && total > maxVal) continue;

        count++;
        sumSub += subtotal;
        sumIva += iva;
        sumTot += total;

        r.__display = { subtotal, iva, total, rfc, razon, ivapct: ivaPct };
        out.push(r);
      }

      state.totals = { count, sub: sumSub, iva: sumIva, tot: sumTot };
      return out;
    }

    let chartTipo = null, chartTopRfc = null, chartFlujo = null;

    function ensureChart(canvas, type, config, prev) {
      if (!canvas || !window.Chart) return prev;
      if (prev && typeof prev.destroy === 'function') prev.destroy();
      return new Chart(canvas, { type, data: config.data, options: config.options || {} });
    }

    function updateMetricsAndCharts() {
      const rows = state.filtered || [];

      const totalCount = state.totals.count || 0;
      let totalEmitidos = 0, totalRecibidos = 0;
      let countEmitidos = 0, countRecibidos = 0;

      const byRfc = new Map();
      const byFecha = new Map();

      for (const r of rows) {
        const disp = r.__display || {};
        const total = disp.total != null ? toNum(disp.total) : toNum(getRowTotal(r));
        const tipo = String(r.tipo || '').toLowerCase();

        const info = disp.rfc ? { rfc: disp.rfc, razon: disp.razon } : getRfcAndRazon(r);
        const rfc = info.rfc || '—';
        const razon = (info.razon || '').trim() || '—';

        if (tipo === 'emitidos') { totalEmitidos += total; countEmitidos++; }
        else if (tipo === 'recibidos') { totalRecibidos += total; countRecibidos++; }

        if (rfc && rfc !== '—') {
          const prev = byRfc.get(rfc) || { rfc, razon, cnt: 0, total: 0 };
          prev.cnt += 1;
          prev.total += total;
          if (!prev.razon && razon) prev.razon = razon;
          byRfc.set(rfc, prev);
        }

        const d = getRowDate(r);
        if (d) {
          const key = d.toISOString().slice(0, 10);
          const prevF = byFecha.get(key) || { fecha: key, total: 0 };
          prevF.total += total;
          byFecha.set(key, prevF);
        }
      }

      if (vmTotalCount) vmTotalCount.textContent = totalCount;
      if (vmTotalEmitidos) vmTotalEmitidos.textContent = countEmitidos;
      if (vmTotalRecibidos) vmTotalRecibidos.textContent = countRecibidos;

      const totGlobal = state.totals.tot || 0;
      if (vmTotGlobal) vmTotGlobal.textContent = fmtMoney(totGlobal);
      if (vmTotEmitidos) vmTotEmitidos.textContent = fmtMoney(totalEmitidos);
      if (vmTotRecibidos) vmTotRecibidos.textContent = fmtMoney(totalRecibidos);

      const avg = totalCount ? (totGlobal / totalCount) : 0;
      if (vmAvgTotal) vmAvgTotal.textContent = fmtMoney(avg);

      let topRfc = '—', topTotal = 0;
      byRfc.forEach(v => { if (v.total > topTotal) { topTotal = v.total; topRfc = v.rfc; } });
      if (vmTopRfc) vmTopRfc.textContent = topRfc || '—';
      if (vmTopRfcTot) vmTopRfcTot.textContent = fmtMoney(topTotal);

      const sumaTipos = totalEmitidos + totalRecibidos;
      const pctEm = sumaTipos > 0 ? (totalEmitidos / sumaTipos) * 100 : 0;
      const pctRec = sumaTipos > 0 ? (totalRecibidos / sumaTipos) * 100 : 0;
      if (vmBarEmitidos) vmBarEmitidos.style.width = pctEm.toFixed(1) + '%';
      if (vmBarRecibidos) vmBarRecibidos.style.width = pctRec.toFixed(1) + '%';

      setText('vqCount', String(totalCount));
      setText('vqTotal', fmtMoney(totGlobal));
      setText('vqSubTotal', fmtMoney(state.totals.sub || 0));
      setText('vqIvaTotal', fmtMoney(state.totals.iva || 0));

      setText('vqEmitCount', String(countEmitidos));
      setText('vqEmitTotal', fmtMoney(totalEmitidos));

      setText('vqRecCount', String(countRecibidos));
      setText('vqRecTotal', fmtMoney(totalRecibidos));

      if (vqTopBody) {
        vqTopBody.innerHTML = '';
        const topList = Array.from(byRfc.values()).filter(v => v.rfc && v.rfc !== '—')
          .sort((a,b) => b.total - a.total).slice(0, 5);

        if (!topList.length) {
          const tr = document.createElement('tr');
          tr.innerHTML = '<td colspan="4" class="vq-empty">Sin datos suficientes todavía…</td>';
          vqTopBody.appendChild(tr);
        } else {
          topList.forEach(row => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
              <td>${row.rfc}</td>
              <td>${(row.razon || '—')}</td>
              <td class="t-center">${String(row.cnt || 0)}</td>
              <td class="t-right">${fmtMoney(row.total || 0)}</td>
            `;
            vqTopBody.appendChild(tr);
          });
        }
      }

      const cTipo = document.getElementById('vaultChartTipo');
      const cTop  = document.getElementById('vaultChartTopRfc');
      const cFlu  = document.getElementById('vaultChartFlujo');

      chartTipo = ensureChart(cTipo, 'doughnut', {
        data: { labels: ['Emitidos','Recibidos'], datasets: [{ data: [totalEmitidos, totalRecibidos] }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => fmtMoney(ctx.parsed) } } },
          cutout: '60%',
        }
      }, chartTipo);

      const rfcArr = Array.from(byRfc.values()).filter(v => v.rfc && v.rfc !== '—')
        .sort((a,b) => b.total - a.total).slice(0, 5);

      chartTopRfc = ensureChart(cTop, 'bar', {
        data: { labels: rfcArr.map(v => v.rfc), datasets: [{ data: rfcArr.map(v => v.total) }] },
        options: {
          indexAxis: 'y',
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => fmtMoney(ctx.parsed.x) } } },
          scales: { x: { ticks: { callback: v => fmtMoney(v) } } }
        }
      }, chartTopRfc);

      const fechasArr = Array.from(byFecha.values()).sort((a,b) => (a.fecha < b.fecha ? -1 : 1));

      chartFlujo = ensureChart(cFlu, 'line', {
        data: { labels: fechasArr.map(v => v.fecha), datasets: [{ data: fechasArr.map(v => v.total), fill: false }] },
        options: {
          responsive: true, maintainAspectRatio: false,
          plugins: { legend: { display: false }, tooltip: { callbacks: { label: ctx => fmtMoney(ctx.parsed.y) } } },
          scales: { y: { ticks: { callback: v => fmtMoney(v) } } }
        }
      }, chartFlujo);
    }

    function render() {
      state.filtered = applyFilters();

      const totalRows  = state.filtered.length;
      const totalPages = Math.max(1, Math.ceil(totalRows / state.pageSize));
      if (state.page > totalPages) state.page = totalPages;

      const startIndex = (state.page - 1) * state.pageSize;
      const endIndex   = Math.min(startIndex + state.pageSize, totalRows);
      const slice      = state.filtered.slice(startIndex, endIndex);

      tbody.innerHTML = '';

      if (!slice.length) {
        const tr = document.createElement('tr');
        tr.innerHTML = '<td colspan="10" class="empty-cell">Sin datos</td>';
        tbody.appendChild(tr);
      } else {
        slice.forEach(r => {
          const tr = document.createElement('tr');

          const d = getRowDate(r);
          const fTxt = d ? d.toISOString().slice(0, 10) : (r.fecha || r.fecha_emision || '');
          const tipo = (r.tipo || '').toString().toUpperCase();

          const info = getRfcAndRazon(r);
          const disp = r.__display || {};

          const rfc = (disp.rfc || info.rfc || '—').toString().toUpperCase();
          const razon = (disp.razon || info.razon || '—').toString();

          tr.appendChild(tdText(fTxt || '—'));
          tr.appendChild(tdText(tipo || '—'));
          tr.appendChild(tdText(rfc || '—'));
          tr.appendChild(tdText(razon || '—'));
          tr.appendChild(tdText(r.uuid || '—'));

          let subR = (disp.subtotal != null) ? toNum(disp.subtotal) : toNum(r.subtotal ?? r.subtotal_mxn ?? 0);
          let ivaR = (disp.iva != null) ? toNum(disp.iva) : toNum(r.iva ?? r.iva_mxn ?? 0);
          let totR = (disp.total != null) ? toNum(disp.total) : toNum(getRowTotal(r));

          if (!Number.isFinite(subR) || subR < 0) subR = 0;
          if (!Number.isFinite(ivaR) || ivaR < 0) ivaR = 0;
          if (!Number.isFinite(totR) || totR < 0) totR = 0;

          if (totR <= 0 && (subR > 0 || ivaR > 0)) totR = subR + ivaR;

          if (ivaR <= 0 && subR > 0 && totR > 0 && totR >= subR) {
            const diff = totR - subR;
            ivaR = diff > 0.00001 ? diff : 0;
          }

          if (subR <= 0 && totR > 0 && ivaR > 0 && totR >= ivaR) {
            const diff = totR - ivaR;
            subR = diff > 0.00001 ? diff : 0;
          }

          let pctR = 0;
          if (subR > 0 && ivaR > 0) {
            pctR = (ivaR / subR) * 100;
            if (!Number.isFinite(pctR) || pctR < 0) pctR = 0;
            if (pctR > 200) pctR = 200;
          }

          r.__display = { subtotal: subR, iva: ivaR, total: totR, rfc, razon, ivapct: pctR };

          tr.appendChild(tdMoney(subR));
          tr.appendChild(tdMoney(ivaR));
          tr.appendChild(tdPctCell(pctR));
          tr.appendChild(tdMoney(totR));

          const tdAcc = document.createElement('td');
          tdAcc.className = 't-actions';

          const uuid = r.uuid || '';
          function makeIconBtn(label, title, act) {
            const btn = document.createElement('button');
            btn.type = 'button';
            btn.className = 'icon-btn';
            btn.title = title;
            btn.dataset.act = act;
            btn.dataset.uuid = uuid;
            btn.textContent = label;
            return btn;
          }

          if (uuid) {
            tdAcc.appendChild(makeIconBtn('XML', 'Descargar XML', 'xml'));
            tdAcc.appendChild(makeIconBtn('PDF', 'Descargar PDF', 'pdf'));
            tdAcc.appendChild(makeIconBtn('ZIP', 'Descargar ZIP', 'zip'));
          }

          tr.appendChild(tdAcc);
          tbody.appendChild(tr);
        });
      }

      const totals = state.totals;
      if (tCnt) tCnt.textContent = totals.count || 0;
      if (tSub) tSub.textContent = fmtMoney(totals.sub || 0);
      if (tIva) tIva.textContent = fmtMoney(totals.iva || 0);
      if (tTot) tTot.textContent = fmtMoney(totals.tot || 0);

      if (pgInfo) {
        const from = totalRows ? (startIndex + 1) : 0;
        const to = endIndex;
        const txt = `${from}–${to} de ${totalRows}`;
        pgInfo.textContent = txt;
        const pgInfoMirror = document.getElementById('pgInfoMirror');
        if (pgInfoMirror) pgInfoMirror.textContent = txt;
      }

      if (pgPrev) pgPrev.disabled = (state.page <= 1);
      if (pgNext) pgNext.disabled = (state.page >= totalPages);

      updateMetricsAndCharts();
    }

    if (pgPrev) pgPrev.addEventListener('click', () => { if (state.page > 1) { state.page--; render(); } });
    if (pgNext) pgNext.addEventListener('click', () => { state.page++; render(); });

    if (pgSize) pgSize.addEventListener('change', () => {
      state.pageSize = Number(pgSize.value || 25) || 25;
      state.page = 1;
      render();
    });

    if (btnApply) btnApply.addEventListener('click', () => { state.page = 1; render(); });

    if (btnClear) btnClear.addEventListener('click', () => {
      if (fTipo)  fTipo.value  = 'ambos';
      if (fRfc)   fRfc.value   = '';
      if (fQuery) fQuery.value = '';
      if (fDesde) fDesde.value = '';
      if (fHasta) fHasta.value = '';
      if (fMin)   fMin.value   = '';
      if (fMax)   fMax.value   = '';
      state.page = 1;
      render();
    });

    if (quickDateBtns && quickDateBtns.length) {
      quickDateBtns.forEach(btn => {
        btn.addEventListener('click', () => {
          const range = btn.dataset.range;
          const today = new Date();
          let d1 = null, d2 = null;

          if (range === 'today') { d1 = new Date(today); d2 = new Date(today); }
          else if (range === 'week') {
            const dow = today.getDay() || 7;
            d2 = new Date(today);
            d1 = new Date(today);
            d1.setDate(d1.getDate() - (dow - 1));
          } else if (range === 'month') {
            d1 = new Date(today.getFullYear(), today.getMonth(), 1);
            d2 = new Date(today.getFullYear(), today.getMonth() + 1, 0);
          } else if (range === 'year') {
            d1 = new Date(today.getFullYear(), 0, 1);
            d2 = new Date(today.getFullYear(), 11, 31);
          } else if (range === 'all') {
            d1 = null; d2 = null;
          }

          if (fDesde) fDesde.value = d1 ? formatDateInput(d1) : '';
          if (fHasta) fHasta.value = d2 ? formatDateInput(d2) : '';

          state.page = 1;
          render();
        });
      });
    }

    if (btnExport) {
      if (!ROUTES.vaultExport) {
        btnExport.disabled = true;
      } else {
        btnExport.addEventListener('click', () => {
          const params = new URLSearchParams();

          if (fTipo && fTipo.value && fTipo.value !== 'ambos') params.set('tipo', fTipo.value);
          if (fRfc && fRfc.value) params.set('rfc', fRfc.value);
          if (fQuery && fQuery.value) params.set('q', fQuery.value);
          if (fDesde && fDesde.value) params.set('desde', fDesde.value);
          if (fHasta && fHasta.value) params.set('hasta', fHasta.value);
          if (fMin && fMin.value) params.set('min', fMin.value);
          if (fMax && fMax.value) params.set('max', fMax.value);

          const base = ROUTES.vaultExport || '';
          const sep  = base.includes('?') ? '&' : '?';
          window.location.href = base + sep + params.toString();
        });
      }
    }

    tbody.addEventListener('click', (ev) => {
      const btn = ev.target.closest('button.icon-btn');
      if (!btn) return;

      const act = btn.dataset.act;
      const uuid = btn.dataset.uuid;
      if (!uuid || !act) return;

      let base = null;
      if (act === 'xml') base = ROUTES.vaultXml;
      else if (act === 'pdf') base = ROUTES.vaultPdf;
      else if (act === 'zip') base = ROUTES.vaultZip;
      if (!base) return;

      const sep = base.includes('?') ? '&' : '?';
      const url = base + sep + 'uuid=' + encodeURIComponent(uuid);
      window.open(url, '_blank');
    });

    render();
  });
  </script>

  {{-- Carrito / helpers SAT --}}
  <script src="{{ asset('assets/client/js/sat-dashboard.js') }}" defer></script>
@endpush
