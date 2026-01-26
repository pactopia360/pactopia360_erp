{{-- resources/views/cliente/sat/_partials/quick_guides.blade.php
     P360 SAT · Quick Guides + Quick Calc Modal (sin RFC) --}}

@php
  // ✅ Rutas opcionales (si no vienen, el JS puede usar window.P360_SAT.routes.*)
  $rtQuickCalc = $rtQuickCalc ?? null;
  $rtQuickPdf  = $rtQuickPdf  ?? null;
@endphp

{{-- 3) Guías rápidas --}}
<div class="sat-card" id="block-quick-guides">
  <div class="sat-quick-head">
    <div class="sat-quick-title-wrap">
      <div class="sat-quick-icon" aria-hidden="true">⚡</div>
      <div>
        <div class="sat-quick-kicker">ATAJOS SAT</div>
        <h3 class="sat-quick-title">Guías rápidas</h3>
        <p class="sat-quick-sub">
          Usa estos atajos para lanzar descargas, cotizar y revisar tu información sin navegar por todo el módulo.
        </p>
      </div>
    </div>

    <div class="sat-quick-plan">
      @if(($isPro ?? false))
        <span class="badge-mode prod"><span class="dot"></span> Plan PRO</span>
      @else
        <span class="badge-mode demo"><span class="dot"></span> Plan FREE</span>
      @endif
    </div>
  </div>

  <div class="pills-row sat-quick-pills">
    {{-- ✅ Calculadora dentro de ATAJOS SAT --}}
    @php
      $qcEnabled = !empty($rtQuickCalc) && !empty($rtQuickPdf);
    @endphp

    <button
      type="button"
      class="pill primary {{ $qcEnabled ? '' : 'is-disabled' }}"
      id="btnQuickCalc"
      data-tip="{{ $qcEnabled ? 'Calcular costo estimado y generar PDF' : 'Calculadora no disponible: faltan rutas (quick.calc / quick.pdf)' }}"
      data-open="modal-quick-calc"
      data-calc-url="{{ $rtQuickCalc ?: '' }}"
      data-pdf-url="{{ $rtQuickPdf ?: '' }}"
      {{ $qcEnabled ? '' : 'disabled' }}
    >
      <span aria-hidden="true">🧮</span><span>Calcular descarga</span>
    </button>

    <button type="button" class="pill" id="btnQuickLast30" data-tip="Crear solicitud para últimos 30 días">
      <span aria-hidden="true">📥</span><span>Descargar últimos 30 días</span>
    </button>

    <button type="button" class="pill" id="btnQuickThisMonth" data-tip="Emitidos y recibidos del mes actual">
      <span aria-hidden="true">🗓️</span><span>Mes actual (emitidos + recibidos)</span>
    </button>

    <button type="button" class="pill" id="btnQuickOnlyEmitted" data-tip="Sólo CFDI emitidos del periodo rápido">
      <span aria-hidden="true">📤</span><span>Sólo emitidos (rápido)</span>
    </button>

    <a href="#block-rfcs" class="pill" data-tip="Ir a la sección Mis RFCs">
      <span aria-hidden="true">🧩</span><span>Administrar RFCs</span>
    </a>

    <a href="{{ $rtVault ?? '#' }}" class="pill" data-tip="Abrir la Bóveda Fiscal">
      <span aria-hidden="true">📚</span><span>Bóveda fiscal</span>
    </a>
  </div>
</div>

{{-- MODAL: CALCULADORA RÁPIDA (GUÍAS RÁPIDAS) · SIN RFC · SOLO COTIZA + PDF --}}
<div class="sat-modal-backdrop" id="modalQuickCalc" style="display:none;">
  <div class="sat-modal sat-modal-lg">
    <div class="sat-modal-header">
      <div>
        <div class="sat-modal-kicker">Calculadora rápida</div>
        <div class="sat-modal-title">Calcular descarga (estimado)</div>
        <p class="sat-modal-sub">
          Esta calculadora NO solicita RFC. Solo estima costo con lista de precios (Admin) y genera PDF.
        </p>
      </div>
      <button type="button" class="sat-modal-close" data-close="modal-quick-calc" aria-label="Cerrar">✕</button>
    </div>

    <div class="sat-modal-body">
      <div class="sat-step-card">
        <div class="sat-step-kicker">
          <span>Parámetros</span>
          <small>Tipo y volumen</small>
        </div>

        <div class="sat-step-grid sat-step-grid-2col">
          <div class="sat-field">
            <div class="sat-field-label">Tipo</div>
            <select class="input sat-input-pill" id="qcTipo">
              <option value="emitidos">Emitidos</option>
              <option value="recibidos">Recibidos</option>
              <option value="ambos" selected>Ambos</option>
            </select>
          </div>

          <div class="sat-field">
            <div class="sat-field-label">Cantidad estimada de XML</div>
            <input class="input sat-input-pill" id="qcXmlCount" type="number" min="1" step="1" placeholder="1000" value="1000">
          </div>

          <div class="sat-field">
            <div class="sat-field-label">Código de descuento (opcional)</div>
            <input class="input sat-input-pill" id="qcDiscountCode" type="text" placeholder="PROMO10">
          </div>

          <div class="sat-field">
            <div class="sat-field-label">IVA</div>
            <select class="input sat-input-pill" id="qcIva">
              <option value="16" selected>16%</option>
              <option value="0">0%</option>
            </select>
          </div>

          <div class="sat-field sat-field-full">
            <div class="text-muted" style="padding:10px 2px;">
              La tarifa se determina por el catálogo y rangos configurados en Admin.
            </div>
          </div>
        </div>
      </div>

      <div class="sat-step-card" style="margin-top:14px;">
        <div class="sat-step-kicker">
          <span>Resultado</span>
          <small>Desglose</small>
        </div>

        <div class="sat-mov-dl" style="margin-top:8px;">
          <div class="sat-mov-row"><dt>Base</dt><dd id="qcBaseVal">$0.00</dd></div>
          <div class="sat-mov-row"><dt>Descuento (<span id="qcDiscPct">0</span>%)</dt><dd id="qcDiscVal">-$0.00</dd></div>
          <div class="sat-mov-row"><dt>Subtotal</dt><dd id="qcSubtotalVal">$0.00</dd></div>
          <div class="sat-mov-row"><dt>IVA (<span id="qcIvaPct">16</span>%)</dt><dd id="qcIvaVal">$0.00</dd></div>
          <div class="sat-mov-row"><dt><b>Total</b></dt><dd><b id="qcTotalVal">$0.00</b></dd></div>
        </div>

        <div class="sat-modal-note" style="margin-top:10px;">
          <span id="qcNote">—</span>
        </div>
      </div>
    </div>

    <div class="sat-modal-footer">
      <button type="button" class="btn" data-close="modal-quick-calc">Cerrar</button>

      <button
        type="button"
        class="btn soft"
        id="btnQcRecalc"
        data-open="modal-quick-calc"
        data-calc-url="{{ $rtQuickCalc ?: '' }}"
      >
        Recalcular
      </button>

      <button
        type="button"
        class="btn primary"
        id="btnQcPdf"
        data-open="modal-quick-calc"
        data-pdf-url="{{ $rtQuickPdf ?: '' }}"
      >
        Generar PDF
      </button>
    </div>
  </div>
</div>
