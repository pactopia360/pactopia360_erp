/* C:\wamp64\www\pactopia360_erp\public\assets\admin\js\invoicing-invoices.js */
(function () {
    'use strict';

    const body = document.body;

    function qs(sel, root = document) {
        return root.querySelector(sel);
    }

    function qsa(sel, root = document) {
        return Array.from(root.querySelectorAll(sel));
    }

    function debounce(fn, wait = 300) {
        let t = null;
        return function (...args) {
            clearTimeout(t);
            t = setTimeout(() => fn.apply(this, args), wait);
        };
    }

    function escapeHtml(value) {
        return String(value ?? '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function lockScroll(lock) {
        body.style.overflow = lock ? 'hidden' : '';
    }

    function syncOverlayScrollState() {
        const anyOpenModal = qsa('.invx-modal[aria-hidden="false"]').length > 0;
        const anyOpenDrawer = qsa('.invx-drawer[aria-hidden="false"]').length > 0;
        lockScroll(anyOpenModal || anyOpenDrawer);
    }

    function openModal(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.setAttribute('aria-hidden', 'false');
        syncOverlayScrollState();
    }

    function closeModal(el) {
        if (!el) return;
        el.setAttribute('aria-hidden', 'true');
        syncOverlayScrollState();
    }

    function openDrawer(id) {
        const el = document.getElementById(id);
        if (!el) return;
        el.setAttribute('aria-hidden', 'false');
        syncOverlayScrollState();
    }

    function closeDrawer(el) {
        if (!el) return;
        el.setAttribute('aria-hidden', 'true');
        syncOverlayScrollState();
    }

    qsa('[data-invx-modal-open]').forEach((btn) => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-invx-modal-open');
            openModal(id);
        });
    });

    qsa('[data-invx-drawer-open]').forEach((btn) => {
        btn.addEventListener('click', function () {
            const id = this.getAttribute('data-invx-drawer-open');
            openDrawer(id);
        });
    });

    qsa('[data-invx-modal-close]').forEach((btn) => {
        btn.addEventListener('click', function () {
            closeModal(this.closest('.invx-modal'));
        });
    });

    qsa('[data-invx-drawer-close]').forEach((btn) => {
        btn.addEventListener('click', function () {
            closeDrawer(this.closest('.invx-drawer'));
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        qsa('.invx-modal[aria-hidden="false"]').forEach(closeModal);
        qsa('.invx-drawer[aria-hidden="false"]').forEach(closeDrawer);
    });

    function syncFloatingStates() {
        qsa('.invx-floating .invx-input, .invx-floating .invx-textarea').forEach((el) => {
            const isSelect = el.tagName === 'SELECT';
            const hasValue = String(el.value || '').trim() !== '';

            if (hasValue || isSelect) {
                el.classList.add('invx-has-value');
            } else {
                el.classList.remove('invx-has-value');
            }
        });
    }

    document.addEventListener('input', function (e) {
        if (e.target.matches('.invx-floating .invx-input, .invx-floating .invx-textarea')) {
            const hasValue = String(e.target.value || '').trim() !== '';
            if (hasValue) {
                e.target.classList.add('invx-has-value');
            } else if (e.target.tagName !== 'SELECT') {
                e.target.classList.remove('invx-has-value');
            }
        }
    });

    document.addEventListener('change', function (e) {
        if (e.target.matches('.invx-floating select')) {
            e.target.classList.add('invx-has-value');
        }
    });

    const bulkIdsInput = qs('#invxBulkInvoiceIds');
    const bulkSendBtn = qs('#invxBulkSendBtn');
    const checkAllHead = qs('#invxCheckAllHead');
    const selectAllBtn = qs('#invxSelectAllBtn');
    const clearAllBtn = qs('#invxClearAllBtn');
    const bulkForm = qs('#invxBulkSendForm');

    function getChecks() {
        return qsa('.invx-row-check');
    }

    function getSelectedIds() {
        return getChecks()
            .filter((el) => el.checked)
            .map((el) => String(el.value).trim())
            .filter((v) => v !== '');
    }

    function syncSelection() {
        const ids = getSelectedIds();

        if (bulkIdsInput) {
            bulkIdsInput.value = ids.join(',');
        }

        if (bulkSendBtn) {
            bulkSendBtn.disabled = ids.length === 0;
            bulkSendBtn.textContent = ids.length > 0
                ? 'Enviar selección (' + ids.length + ')'
                : 'Enviar selección';
        }

        if (checkAllHead) {
            const checks = getChecks();
            const total = checks.length;
            const checked = ids.length;
            checkAllHead.checked = total > 0 && checked === total;
            checkAllHead.indeterminate = checked > 0 && checked < total;
        }
    }

    if (checkAllHead) {
        checkAllHead.addEventListener('change', function () {
            const checked = !!this.checked;
            getChecks().forEach((el) => { el.checked = checked; });
            syncSelection();
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            getChecks().forEach((el) => { el.checked = true; });
            syncSelection();
        });
    }

    if (clearAllBtn) {
        clearAllBtn.addEventListener('click', function () {
            getChecks().forEach((el) => { el.checked = false; });
            syncSelection();
        });
    }

    getChecks().forEach((el) => {
        el.addEventListener('change', syncSelection);
    });

    if (bulkForm) {
        bulkForm.addEventListener('submit', function (e) {
            const ids = getSelectedIds();
            if (!ids.length) {
                e.preventDefault();
                alert('Selecciona al menos una factura para envío masivo.');
                return;
            }
            if (bulkIdsInput) {
                bulkIdsInput.value = ids.join(',');
            }
        });
    }

    const bulkTableBody = qs('#invxBulkTable tbody');
    const addBulkRowBtn = qs('#invxAddBulkRow');

    if (addBulkRowBtn && bulkTableBody) {
        addBulkRowBtn.addEventListener('click', function () {
            const rowCount = bulkTableBody.querySelectorAll('tr').length;
            const i = rowCount;

            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td>${i + 1}</td>
                <td><input type="text" name="account_id[]" class="invx-bulk-input" placeholder="Cuenta"></td>
                <td><input type="text" name="period[]" class="invx-bulk-input" placeholder="YYYY-MM"></td>
                <td><input type="text" name="cfdi_uuid[]" class="invx-bulk-input" placeholder="UUID"></td>
                <td><input type="text" name="serie[]" class="invx-bulk-input" placeholder="Serie"></td>
                <td><input type="text" name="folio[]" class="invx-bulk-input" placeholder="Folio"></td>
                <td><input type="text" name="status[]" class="invx-bulk-input" placeholder="issued" value="issued"></td>
                <td><input type="text" name="amount_mxn[]" class="invx-bulk-input" placeholder="0.00"></td>
                <td><input type="text" name="issued_at[]" class="invx-bulk-input" placeholder="2026-03-11 10:00:00"></td>
                <td><input type="text" name="issued_date[]" class="invx-bulk-input" placeholder="2026-03-11"></td>
                <td><input type="text" name="source[]" class="invx-bulk-input" placeholder="manual_bulk_admin" value="manual_bulk_admin"></td>
                <td><input type="text" name="notes[]" class="invx-bulk-input" placeholder="Notas"></td>
                <td><input type="file" name="pdf_files[${i}]" class="invx-bulk-input" accept="application/pdf"></td>
                <td><input type="file" name="xml_files[${i}]" class="invx-bulk-input" accept=".xml,application/xml,text/xml,.txt,text/plain"></td>
            `;
            bulkTableBody.appendChild(tr);
        });
    }

    const sendForm = qs('#invxSendInvoiceForm');
    const sendTo = qs('#invxSendTo');
    const sendTitle = qs('#invxSendModalTitle');
    const sendSubmit = qs('#invxSendInvoiceSubmit');

    qsa('[data-invx-open-send]').forEach((btn) => {
        btn.addEventListener('click', function () {
            const action = this.getAttribute('data-action') || '#';
            const to = this.getAttribute('data-to') || '';
            const title = this.getAttribute('data-title') || 'Enviar factura';
            const mode = this.getAttribute('data-mode') || 'send';

            if (sendForm) sendForm.setAttribute('action', action);
            if (sendTo) sendTo.value = to;
            if (sendTitle) sendTitle.textContent = title;
            if (sendSubmit) {
                sendSubmit.textContent = mode === 'resend' ? 'Reenviar factura' : 'Enviar factura';
                sendSubmit.className = mode === 'resend'
                    ? 'invx-btn invx-btn--warn'
                    : 'invx-btn invx-btn--primary';
            }

            syncFloatingStates();
            openModal('sendInvoiceModal');
        });
    });

    const confirmForm = qs('#invxConfirmForm');
    const confirmTitle = qs('#invxConfirmTitle');
    const confirmMessage = qs('#invxConfirmMessage');
    const confirmSubmit = qs('#invxConfirmSubmit');

    qsa('[data-invx-open-confirm]').forEach((btn) => {
        btn.addEventListener('click', function () {
            const action = this.getAttribute('data-action') || '#';
            const title = this.getAttribute('data-title') || 'Confirmar acción';
            const message = this.getAttribute('data-message') || '¿Deseas continuar?';
            const confirmLabel = this.getAttribute('data-confirm-label') || 'Confirmar';
            const kind = this.getAttribute('data-kind') || 'default';

            if (confirmForm) confirmForm.setAttribute('action', action);
            if (confirmTitle) confirmTitle.textContent = title;
            if (confirmMessage) confirmMessage.textContent = message;

            if (confirmSubmit) {
                confirmSubmit.textContent = confirmLabel;
                confirmSubmit.className = kind === 'stamp'
                    ? 'invx-btn invx-btn--info'
                    : 'invx-btn invx-btn--danger';
            }

            openModal('confirmActionModal');
        });
    });

    if (window.__INVX_AUTO_OPEN_SINGLE__) {
        openDrawer('singleDrawer');
    }

    const seedUrl = window.__INVX_FORM_SEED__ || '';
    const searchEmisoresUrl = window.__INVX_SEARCH_EMISORES__ || '';
    const searchReceptoresUrl = window.__INVX_SEARCH_RECEPTORES__ || '';

    const emisorSearchInput = qs('#invx_emisor_search');
    const receptorSearchInput = qs('#invx_receptor_search');

    const emisorResults = qs('#invx_emisor_results');
    const receptorResults = qs('#invx_receptor_results');

    const pickedEmisor = qs('#invxPickedEmisor');
    const pickedReceptor = qs('#invxPickedReceptor');

    const emisorIdInput = qs('#invx_emisor_id');
    const receptorIdInput = qs('#invx_receptor_id');

    const accountIdInput = qs('#manual_account_id');
    const periodInput = qs('#manual_period');
    const manualToInput = qs('#manual_to');
    const manualSourceInput = qs('#manual_source');
    const manualIssuedAtInput = qs('#manual_issued_at');
    const manualIssuedDateInput = qs('#manual_issued_date');
    const manualUuidInput = qs('#manual_cfdi_uuid');
    const manualAmountInput = qs('#manual_amount_mxn');
    const manualSerieInput = qs('#manual_serie');
    const manualFolioInput = qs('#manual_folio');
    const globalSearchInput = qs('#q');

    const usoCfdiSelect = qs('#invx_uso_cfdi');
    const regimenSelect = qs('#invx_regimen_fiscal');
    const formaPagoSelect = qs('#invx_forma_pago');
    const metodoPagoSelect = qs('#invx_metodo_pago');
    const monedaSelect = qs('#invx_moneda');
    const exportacionSelect = qs('#invx_exportacion');
    const objetoImpuestoSelect = qs('#invx_objeto_impuesto');
    const complementoSelect = qs('#invx_complemento');
    const tipoComprobanteSelect = qs('#invx_tipo_comprobante');

    const aiPrompt = qs('#invxAiPrompt');
    const aiResponseBox = qs('#invxAiResponseBox');
    const aiComposeBtn = qs('#invxAiComposeBtn');
    const aiExplainBtn = qs('#invxAiExplainStepBtn');
    const aiReviewBtn = qs('#invxAiReviewBtn');

    const summaryTipo = qs('#invxSummaryTipo');
    const summaryCuenta = qs('#invxSummaryCuenta');
    const summaryMonto = qs('#invxSummaryMonto');
    const summaryPeriodo = qs('#invxSummaryPeriodo');
    const summaryAdjuntos = qs('#invxSummaryAdjuntos');
    const summaryMetodo = qs('#invxSummaryMetodo');
    const summaryComplemento = qs('#invxSummaryComplemento');

    const reviewTipo = qs('#invxReviewTipo');
    const reviewCuenta = qs('#invxReviewCuenta');
    const reviewPeriodo = qs('#invxReviewPeriodo');
    const reviewMonto = qs('#invxReviewMonto');
    const reviewEstado = qs('#invxReviewEstado');
    const reviewMetodo = qs('#invxReviewMetodo');
    const reviewForma = qs('#invxReviewForma');
    const reviewMoneda = qs('#invxReviewMoneda');
    const reviewAdjuntos = qs('#invxReviewAdjuntos');
    const reviewClonado = qs('#invxReviewClonado');

    const tipoActualLabel = qs('#invxTipoActualLabel');
    const tipoSmartText = qs('#invxTipoSmartText');
    const satHintBox = qs('#invxSatHintBox');
    const aiPreflightBox = qs('#invxAiPreflightBox');
    const cloneModeHidden = qs('#invx_clone_mode');
    const cloneModeLabel = qs('#invxCloneModeLabel');
    const cloneModeText = qs('#invxCloneModeText');
    const ppdAlertBox = qs('#invxPpdAlertBox');

    const openSingleAIAssist = qs('#invxOpenSingleAIAssist');
    const assistToday = qs('#invxAssistToday');
    const assistCurrentPeriod = qs('#invxAssistCurrentPeriod');
    const assistDefaultSource = qs('#invxAssistDefaultSource');
    const assistClearUuid = qs('#invxAssistClearUuid');
    const assistFocusEmisor = qs('#invxAssistFocusEmisor');
    const assistFocusReceptor = qs('#invxAssistFocusReceptor');

    const useCloneModeBtn = qs('#invxUseCloneMode');
    const skipCloneModeBtn = qs('#invxSkipCloneMode');

    const stepLabelMap = {
        1: 'tipo de comprobante',
        2: 'clonado o captura desde cero',
        3: 'datos generales',
        4: 'emisor y receptor',
        5: 'datos SAT',
        6: 'adjuntos',
        7: 'revisión final'
    };

    const tipoHelpMap = {
        I: 'Ingreso: recomendado para facturas por venta de productos o prestación de servicios.',
        E: 'Egreso: ideal para devoluciones, bonificaciones, descuentos o notas de crédito.',
        P: 'Pago: se usa para complementos de pago. Este flujo será clave para parcialidades y pagos PPD.',
        N: 'Nómina: enfocado a recibos de nómina y validaciones específicas.',
        T: 'Traslado: útil para traslado de mercancías; puede requerir complementos adicionales.'
    };

    let seedPayload = null;
    let currentWizardStep = 1;

    function formatMoney(value) {
        const n = parseFloat(String(value || '0').replace(/,/g, ''));
        if (!Number.isFinite(n)) return '$0.00';
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(n);
    }

    function optionText(select) {
        if (!select || !select.options || select.selectedIndex < 0) return '—';
        return String(select.options[select.selectedIndex]?.text || '—').trim();
    }

    function optionValue(select) {
        if (!select) return '';
        return String(select.value || '').trim();
    }

    function getNowParts() {
        const now = new Date();
        return {
            yyyy: String(now.getFullYear()),
            mm: String(now.getMonth() + 1).padStart(2, '0'),
            dd: String(now.getDate()).padStart(2, '0'),
            hh: String(now.getHours()).padStart(2, '0'),
            ii: String(now.getMinutes()).padStart(2, '0')
        };
    }

    function fillCurrentPeriod(force = false) {
        const p = getNowParts();
        const ym = p.yyyy + '-' + p.mm;

        const filterPeriod = qs('#period');
        if (filterPeriod && (force || !filterPeriod.value.trim())) {
            filterPeriod.value = ym;
        }

        if (periodInput && (force || !periodInput.value.trim())) {
            periodInput.value = ym;
        }

        syncFloatingStates();
        syncWizardSummary();
    }

    function fillTodayFields(force = false) {
        const p = getNowParts();
        if (manualIssuedDateInput && (force || !manualIssuedDateInput.value)) {
            manualIssuedDateInput.value = `${p.yyyy}-${p.mm}-${p.dd}`;
        }
        if (manualIssuedAtInput && (force || !manualIssuedAtInput.value)) {
            manualIssuedAtInput.value = `${p.yyyy}-${p.mm}-${p.dd}T${p.hh}:${p.ii}`;
        }
        syncFloatingStates();
        syncWizardSummary();
    }

    function fillDefaultSource(force = false) {
        if (manualSourceInput && (force || !manualSourceInput.value.trim())) {
            manualSourceInput.value = 'manual_admin_ai';
        }
        syncFloatingStates();
    }

    function clearUuid() {
        if (manualUuidInput) {
            manualUuidInput.value = '';
        }
        syncFloatingStates();
        syncWizardSummary();
    }

    function fillSelect(select, items, placeholder, preferredValue = '') {
        if (!select) return;

        const currentValue = preferredValue || String(select.value || '').trim();
        const arr = Array.isArray(items) ? items : [];

        let html = `<option value="">${escapeHtml(placeholder || 'Selecciona')}</option>`;

        arr.forEach((item) => {
            const clave = String(item.clave ?? item.value ?? '');
            const descripcion = String(item.descripcion ?? item.label ?? clave);
            html += `<option value="${escapeHtml(clave)}">${escapeHtml(clave)}${descripcion && descripcion !== clave ? ' · ' + escapeHtml(descripcion) : ''}</option>`;
        });

        select.innerHTML = html;

        if (currentValue !== '') {
            select.value = currentValue;
        }

        select.classList.add('invx-has-value');
    }

    async function loadSeedCatalogs() {
        if (!seedUrl) return;

        try {
            const res = await fetch(seedUrl, {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!res.ok) return;

            const json = await res.json();
            seedPayload = json;

            const data = json.data || json;
            const defaults = json.defaults || {};

            fillSelect(usoCfdiSelect, data.usos_cfdi || [], 'Uso CFDI', defaults.uso_cfdi || '');
            fillSelect(regimenSelect, data.regimenes_fiscales || [], 'Régimen fiscal');
            fillSelect(formaPagoSelect, data.formas_pago || [], 'Forma de pago', defaults.forma_pago || '');
            fillSelect(metodoPagoSelect, data.metodos_pago || [], 'Método de pago', defaults.metodo_pago || '');
            fillSelect(monedaSelect, data.monedas || [], 'Moneda', defaults.moneda || '');
            fillSelect(exportacionSelect, data.exportaciones || [], 'Exportación', defaults.exportacion || '');
            fillSelect(objetoImpuestoSelect, data.objetos_impuesto || [], 'Objeto impuesto', defaults.objeto_impuesto || '');

            if (manualSourceInput && !manualSourceInput.value.trim() && defaults.source) {
                manualSourceInput.value = defaults.source;
            }

            syncFloatingStates();
            syncWizardSummary();
            syncPpdUiState();
            syncAiHints();
        } catch (e) {
            // silencioso
        }
    }

    function getTipoLabel(value) {
        const map = {
            I: 'Ingreso',
            E: 'Egreso',
            P: 'Pago',
            N: 'Nómina',
            T: 'Traslado'
        };
        return map[value] || 'Ingreso';
    }

    function applyTipoVisualState() {
        const tipo = optionValue(tipoComprobanteSelect) || 'I';

        qsa('.invx-typebtn').forEach((btn) => {
            btn.classList.toggle('is-active', btn.getAttribute('data-value') === tipo);
        });

        const label = getTipoLabel(tipo);
        if (summaryTipo) summaryTipo.textContent = label;
        if (reviewTipo) reviewTipo.textContent = label;
        if (tipoActualLabel) tipoActualLabel.textContent = label;
        if (tipoSmartText) tipoSmartText.textContent = tipoHelpMap[tipo] || tipoHelpMap.I;
    }

    function renderSearchResults(box, rows, type) {
        if (!box) return;

        const arr = Array.isArray(rows) ? rows : [];

        if (!arr.length) {
            box.innerHTML = `<div class="invx-search-results__item"><div class="invx-search-results__meta">Sin resultados.</div></div>`;
            box.classList.add('is-open');
            return;
        }

        box.innerHTML = arr.map((row) => {
            const id = String(row.id ?? '');
            const rfc = String(row.rfc ?? '');
            const razon = String(row.razon_social ?? row.nombre ?? '');
            const email = String(row.email ?? '');
            const cuentaId = String(row.cuenta_id ?? row.account_id ?? '');
            const cp = String(row.codigo_postal ?? row.cp ?? '');
            const regimen = String(row.regimen_fiscal ?? row.regimen ?? '');
            const extId = String(row.ext_id ?? '');
            const usoCfdi = String(row.uso_cfdi ?? '');
            const formaPago = String(row.forma_pago ?? '');
            const metodoPago = String(row.metodo_pago ?? '');

            return `
                <div class="invx-search-results__item"
                     data-pick-type="${escapeHtml(type)}"
                     data-id="${escapeHtml(id)}"
                     data-rfc="${escapeHtml(rfc)}"
                     data-razon="${escapeHtml(razon)}"
                     data-email="${escapeHtml(email)}"
                     data-cuenta-id="${escapeHtml(cuentaId)}"
                     data-cp="${escapeHtml(cp)}"
                     data-regimen="${escapeHtml(regimen)}"
                     data-ext-id="${escapeHtml(extId)}"
                     data-uso-cfdi="${escapeHtml(usoCfdi)}"
                     data-forma-pago="${escapeHtml(formaPago)}"
                     data-metodo-pago="${escapeHtml(metodoPago)}">
                    <div class="invx-search-results__title">${escapeHtml(razon || '(Sin razón social)')}</div>
                    <div class="invx-search-results__meta">
                        RFC: ${escapeHtml(rfc || '—')}
                        ${email ? ' · Email: ' + escapeHtml(email) : ''}
                        ${cuentaId ? ' · Cuenta: ' + escapeHtml(cuentaId) : ''}
                        ${cp ? ' · CP: ' + escapeHtml(cp) : ''}
                        ${extId ? ' · ext_id: ' + escapeHtml(extId) : ''}
                    </div>
                </div>
            `;
        }).join('');

        box.classList.add('is-open');

        qsa('.invx-search-results__item[data-pick-type]', box).forEach((item) => {
            item.addEventListener('click', function () {
                const payload = {
                    id: this.getAttribute('data-id') || '',
                    rfc: this.getAttribute('data-rfc') || '',
                    razon: this.getAttribute('data-razon') || '',
                    email: this.getAttribute('data-email') || '',
                    cuentaId: this.getAttribute('data-cuenta-id') || '',
                    cp: this.getAttribute('data-cp') || '',
                    regimen: this.getAttribute('data-regimen') || '',
                    extId: this.getAttribute('data-ext-id') || '',
                    usoCfdi: this.getAttribute('data-uso-cfdi') || '',
                    formaPago: this.getAttribute('data-forma-pago') || '',
                    metodoPago: this.getAttribute('data-metodo-pago') || ''
                };

                const pickType = this.getAttribute('data-pick-type');

                if (pickType === 'emisor') {
                    if (pickedEmisor) {
                        pickedEmisor.innerHTML = `
                            <div><b>${escapeHtml(payload.razon || 'Sin nombre')}</b></div>
                            <div>RFC: ${escapeHtml(payload.rfc || '—')}</div>
                            <div>Email: ${escapeHtml(payload.email || '—')}</div>
                            <div>Cuenta: ${escapeHtml(payload.cuentaId || '—')}</div>
                            <div>ext_id: ${escapeHtml(payload.extId || '—')}</div>
                        `;
                    }

                    if (emisorIdInput) emisorIdInput.value = payload.id;
                    if (accountIdInput && payload.cuentaId) accountIdInput.value = payload.cuentaId;
                    if (emisorSearchInput) emisorSearchInput.value = payload.razon || payload.rfc || '';
                    box.classList.remove('is-open');

                    if (aiResponseBox) {
                        aiResponseBox.innerHTML = `<div><strong>IA:</strong> Emisor seleccionado correctamente. Ya intenté vincular la cuenta si estaba disponible.</div>`;
                    }
                }

                if (pickType === 'receptor') {
                    if (pickedReceptor) {
                        pickedReceptor.innerHTML = `
                            <div><b>${escapeHtml(payload.razon || 'Sin nombre')}</b></div>
                            <div>RFC: ${escapeHtml(payload.rfc || '—')}</div>
                            <div>Email: ${escapeHtml(payload.email || '—')}</div>
                            <div>CP: ${escapeHtml(payload.cp || '—')}</div>
                            <div>Régimen: ${escapeHtml(payload.regimen || '—')}</div>
                        `;
                    }

                    if (receptorIdInput) receptorIdInput.value = payload.id;
                    if (manualToInput && payload.email && !manualToInput.value.trim()) manualToInput.value = payload.email;
                    if (regimenSelect && payload.regimen) regimenSelect.value = payload.regimen;
                    if (usoCfdiSelect && payload.usoCfdi) usoCfdiSelect.value = payload.usoCfdi;
                    if (formaPagoSelect && payload.formaPago) formaPagoSelect.value = payload.formaPago;
                    if (metodoPagoSelect && payload.metodoPago) metodoPagoSelect.value = payload.metodoPago;
                    if (receptorSearchInput) receptorSearchInput.value = payload.razon || payload.rfc || '';
                    box.classList.remove('is-open');

                    if (aiResponseBox) {
                        aiResponseBox.innerHTML = `<div><strong>IA:</strong> Receptor seleccionado. También sugerí uso CFDI, régimen fiscal y forma/método de pago cuando venían disponibles.</div>`;
                    }
                }

                syncFloatingStates();
                syncWizardSummary();
                syncPpdUiState();
                syncAiHints();
            });
        });
    }

    async function doSearch(url, query, box, type) {
        if (!url || !box) return;

        const q = String(query || '').trim();
        if (q.length < 2) {
            box.innerHTML = '';
            box.classList.remove('is-open');
            return;
        }

        try {
            const u = new URL(url, window.location.origin);
            u.searchParams.set('q', q);

            const res = await fetch(u.toString(), {
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                }
            });

            if (!res.ok) {
                box.innerHTML = '';
                box.classList.remove('is-open');
                return;
            }

            const json = await res.json();
            const rows = json.data || json.rows || json.items || [];
            renderSearchResults(box, rows, type);
        } catch (e) {
            box.innerHTML = '';
            box.classList.remove('is-open');
        }
    }

    if (emisorSearchInput && emisorResults) {
        emisorSearchInput.addEventListener('input', debounce(function () {
            doSearch(searchEmisoresUrl, this.value, emisorResults, 'emisor');
        }, 250));
    }

    if (receptorSearchInput && receptorResults) {
        receptorSearchInput.addEventListener('input', debounce(function () {
            doSearch(searchReceptoresUrl, this.value, receptorResults, 'receptor');
        }, 250));
    }

    document.addEventListener('click', function (e) {
        if (emisorResults && !emisorResults.contains(e.target) && e.target !== emisorSearchInput) {
            emisorResults.classList.remove('is-open');
        }
        if (receptorResults && !receptorResults.contains(e.target) && e.target !== receptorSearchInput) {
            receptorResults.classList.remove('is-open');
        }
    });

    function getWizardCurrentStep() {
        const activePane = qs('[data-step-pane].is-active');
        if (!activePane) return 1;
        return parseInt(activePane.getAttribute('data-step-pane') || '1', 10) || 1;
    }

    function syncCurrentStepMemory() {
        currentWizardStep = getWizardCurrentStep();
    }

    function getSelectedFilesCount() {
        const pdf = qs('#manual_pdf');
        const xml = qs('#manual_xml');

        const pdfCount = (pdf && pdf.files && pdf.files.length) ? 1 : 0;
        const xmlCount = (xml && xml.files && xml.files.length) ? 1 : 0;

        return pdfCount + xmlCount;
    }

    function syncWizardSummary() {
        syncCurrentStepMemory();

        const tipoLabel = getTipoLabel(optionValue(tipoComprobanteSelect) || 'I');
        const account = accountIdInput ? String(accountIdInput.value || '').trim() : '';
        const amount = manualAmountInput ? String(manualAmountInput.value || '').trim() : '';
        const period = periodInput ? String(periodInput.value || '').trim() : '';
        const methodText = optionText(metodoPagoSelect);
        const formaText = optionText(formaPagoSelect);
        const monedaText = optionText(monedaSelect);
        const complementoText = optionText(complementoSelect);
        const filesCount = getSelectedFilesCount();
        const statusText = optionText(qs('#manual_status'));

        if (summaryTipo) summaryTipo.textContent = tipoLabel;
        if (summaryCuenta) summaryCuenta.textContent = account || '—';
        if (summaryMonto) summaryMonto.textContent = formatMoney(amount || '0');
        if (summaryPeriodo) summaryPeriodo.textContent = period || '—';
        if (summaryAdjuntos) summaryAdjuntos.textContent = String(filesCount);
        if (summaryMetodo) summaryMetodo.textContent = methodText;
        if (summaryComplemento) summaryComplemento.textContent = complementoText;

        if (reviewTipo) reviewTipo.textContent = tipoLabel;
        if (reviewCuenta) reviewCuenta.textContent = account || '—';
        if (reviewPeriodo) reviewPeriodo.textContent = period || '—';
        if (reviewMonto) reviewMonto.textContent = formatMoney(amount || '0');
        if (reviewEstado) reviewEstado.textContent = statusText;
        if (reviewMetodo) reviewMetodo.textContent = methodText;
        if (reviewForma) reviewForma.textContent = formaText;
        if (reviewMoneda) reviewMoneda.textContent = monedaText;
        if (reviewAdjuntos) reviewAdjuntos.textContent = String(filesCount);

        if (reviewClonado && cloneModeHidden) {
            reviewClonado.textContent = cloneModeHidden.value === 'clone' ? 'XML' : 'Omitir';
        }

        applyTipoVisualState();
    }

    function syncPpdUiState() {
        const metodoVal = optionValue(metodoPagoSelect).toUpperCase();
        const complementoVal = optionValue(complementoSelect).toLowerCase();
        const tipoVal = optionValue(tipoComprobanteSelect).toUpperCase();

        const isPpd = metodoVal === 'PPD';
        const isPago = tipoVal === 'P';
        const isPagoComplement = complementoVal === 'pago20';

        if (ppdAlertBox) {
            ppdAlertBox.classList.toggle('is-visible', isPpd || isPago || isPagoComplement);
        }

        if (satHintBox) {
            if (isPpd) {
                satHintBox.innerHTML = '<div><strong>IA SAT:</strong> Detecté método PPD. Esta factura deberá mostrar posteriormente saldo pendiente, parcialidades y complementos de pago.</div>';
            } else if (metodoVal === 'PUE') {
                satHintBox.innerHTML = '<div><strong>IA SAT:</strong> Detecté método PUE. Normalmente se liquida en una sola exhibición.</div>';
            } else if (isPago || isPagoComplement) {
                satHintBox.innerHTML = '<div><strong>IA SAT:</strong> Estás en un flujo relacionado con pagos/complementos. Después conectaremos la administración de pagos desde el detalle y el listado.</div>';
            } else {
                satHintBox.innerHTML = '<div>La IA te ayudará a revisar compatibilidad entre uso CFDI, régimen, forma de pago y método de pago.</div>';
            }
        }

        if (aiPreflightBox) {
            if (isPpd) {
                aiPreflightBox.innerHTML = '<div><strong>IA:</strong> Flujo PPD detectado. Después de emitir, esta factura deberá manejar pagos parciales, saldo insoluto y varios complementos si aplica.</div>';
            } else if (metodoVal === 'PUE') {
                aiPreflightBox.innerHTML = '<div><strong>IA:</strong> Flujo PUE detectado. La liquidación normalmente ocurre en una sola exhibición.</div>';
            } else if (isPago || isPagoComplement) {
                aiPreflightBox.innerHTML = '<div><strong>IA:</strong> Flujo de pago/complemento detectado. Este tipo quedará ligado a la futura administración de pagos.</div>';
            } else {
                aiPreflightBox.innerHTML = '<div>La IA revisará la consistencia básica del formulario antes de generar la factura.</div>';
            }
        }
    }

    function syncAiHints() {
        const stepName = stepLabelMap[currentWizardStep] || 'flujo actual';
        const tipoVal = optionValue(tipoComprobanteSelect) || 'I';
        const tipoLabel = getTipoLabel(tipoVal);

        if (!aiResponseBox) return;

        if (currentWizardStep === 1) {
            aiResponseBox.innerHTML = `<div><strong>IA:</strong> Estás en <b>${escapeHtml(stepName)}</b>. Actualmente elegiste <b>${escapeHtml(tipoLabel)}</b>. ${escapeHtml(tipoHelpMap[tipoVal] || '')}</div>`;
            return;
        }

        if (currentWizardStep === 2) {
            aiResponseBox.innerHTML = '<div><strong>IA:</strong> Aquí puedes ahorrar tiempo clonando desde XML o continuar desde cero. Si ya tienes un XML parecido, úsalo como base.</div>';
            return;
        }

        if (currentWizardStep === 3) {
            aiResponseBox.innerHTML = '<div><strong>IA:</strong> Captura cuenta, periodo, monto y fechas. Si esta operación no se liquida hoy, probablemente el flujo correcto después será PPD.</div>';
            return;
        }

        if (currentWizardStep === 4) {
            aiResponseBox.innerHTML = '<div><strong>IA:</strong> Busca primero al receptor correcto. Si el registro tiene régimen, uso CFDI o método de pago sugerido, los intentaré aplicar automáticamente.</div>';
            return;
        }

        if (currentWizardStep === 5) {
            aiResponseBox.innerHTML = '<div><strong>IA:</strong> Este paso es clave. Revisa que uso CFDI, régimen fiscal, forma de pago y método de pago sean consistentes.</div>';
            return;
        }

        if (currentWizardStep === 6) {
            aiResponseBox.innerHTML = '<div><strong>IA:</strong> Adjunta PDF o XML base. El XML además te puede ayudar a validar datos del comprobante.</div>';
            return;
        }

        aiResponseBox.innerHTML = '<div><strong>IA:</strong> Ya estás en revisión final. Antes de emitir, verifica monto, partes, método de pago y adjuntos.</div>';
    }

    function setCloneMode(mode) {
        if (!cloneModeHidden) return;

        cloneModeHidden.value = mode;

        if (cloneModeLabel) {
            cloneModeLabel.textContent = mode === 'clone' ? 'Clonar desde XML' : 'Omitir clonado';
        }

        if (cloneModeText) {
            cloneModeText.textContent = mode === 'clone'
                ? 'Se intentará usar el XML como base de captura.'
                : 'Continuarás capturando la factura paso a paso.';
        }

        if (reviewClonado) {
            reviewClonado.textContent = mode === 'clone' ? 'XML' : 'Omitir';
        }

        if (aiResponseBox) {
            aiResponseBox.innerHTML = mode === 'clone'
                ? '<div><strong>IA:</strong> Activé el modo clonado desde XML. Cuando subas el archivo podremos usarlo como base del flujo.</div>'
                : '<div><strong>IA:</strong> Activé el modo captura desde cero. Seguimos con el flujo guiado paso a paso.</div>';
        }
    }

    function bindCloneModeButtons() {
        if (useCloneModeBtn) {
            useCloneModeBtn.addEventListener('click', function () {
                setCloneMode('clone');
                syncWizardSummary();
            });
        }

        if (skipCloneModeBtn) {
            skipCloneModeBtn.addEventListener('click', function () {
                setCloneMode('skip');
                syncWizardSummary();
            });
        }
    }

    function detectPpdIntent(text) {
        const normalized = String(text || '').toLowerCase();
        return normalized.includes('ppd') ||
            normalized.includes('parcialidad') ||
            normalized.includes('parcialidades') ||
            normalized.includes('pendiente') ||
            normalized.includes('complemento de pago') ||
            normalized.includes('complementos de pago');
    }

    function detectPueIntent(text) {
        const normalized = String(text || '').toLowerCase();
        return normalized.includes('pue') ||
            normalized.includes('una sola exhibición') ||
            normalized.includes('pago inmediato') ||
            normalized.includes('liquidada hoy');
    }

    function detectTipoIntent(text) {
        const normalized = String(text || '').toLowerCase();

        if (normalized.includes('egreso') || normalized.includes('nota de crédito') || normalized.includes('devolución')) {
            return 'E';
        }
        if (normalized.includes('nómina') || normalized.includes('nomina')) {
            return 'N';
        }
        if (normalized.includes('traslado') || normalized.includes('carta porte')) {
            return 'T';
        }
        if (normalized.includes('complemento') || normalized.includes('pago 2.0') || normalized.includes('recepción de pagos')) {
            return 'P';
        }
        return 'I';
    }

    function applyAiPresetFromPrompt(text) {
        const normalized = String(text || '').trim();
        if (!normalized) {
            if (aiResponseBox) {
                aiResponseBox.innerHTML = '<div><strong>IA:</strong> Escribe una descripción para que pueda ayudarte a llenar el flujo.</div>';
            }
            return;
        }

        const tipo = detectTipoIntent(normalized);
        if (tipoComprobanteSelect) {
            tipoComprobanteSelect.value = tipo;
            tipoComprobanteSelect.dispatchEvent(new Event('change', { bubbles: true }));
        }

        if (detectPpdIntent(normalized)) {
            if (metodoPagoSelect) {
                const opt = Array.from(metodoPagoSelect.options || []).find((o) => String(o.value || '').toUpperCase() === 'PPD');
                if (opt) metodoPagoSelect.value = opt.value;
            }
            if (complementoSelect && tipo === 'P') {
                complementoSelect.value = 'pago20';
            }
        } else if (detectPueIntent(normalized)) {
            if (metodoPagoSelect) {
                const opt = Array.from(metodoPagoSelect.options || []).find((o) => String(o.value || '').toUpperCase() === 'PUE');
                if (opt) metodoPagoSelect.value = opt.value;
            }
        }

        if (normalized.toLowerCase().includes('transferencia') && formaPagoSelect) {
            const opt = Array.from(formaPagoSelect.options || []).find((o) => String(o.value || '').trim() === '03');
            if (opt) formaPagoSelect.value = opt.value;
        }

        if (normalized.toLowerCase().includes('mxn') && monedaSelect) {
            const opt = Array.from(monedaSelect.options || []).find((o) => String(o.value || '').trim().toUpperCase() === 'MXN');
            if (opt) monedaSelect.value = opt.value;
        }

        fillDefaultSource(true);
        fillCurrentPeriod(false);
        fillTodayFields(false);

        if (aiResponseBox) {
            aiResponseBox.innerHTML = '<div><strong>IA:</strong> Apliqué una configuración inicial con base en tu descripción. Revisa tipo, método, forma de pago y continúa con el wizard.</div>';
        }

        syncFloatingStates();
        syncWizardSummary();
        syncPpdUiState();
        syncAiHints();
    }

    function bindAiButtons() {
        if (aiComposeBtn) {
            aiComposeBtn.addEventListener('click', function () {
                applyAiPresetFromPrompt(aiPrompt ? aiPrompt.value : '');
            });
        }

        if (aiExplainBtn) {
            aiExplainBtn.addEventListener('click', function () {
                syncCurrentStepMemory();

                if (!aiResponseBox) return;

                const stepName = stepLabelMap[currentWizardStep] || 'flujo actual';
                aiResponseBox.innerHTML = `<div><strong>IA:</strong> Estás en <b>${escapeHtml(stepName)}</b>. Te recomiendo completar solo lo esencial de este paso y seguir al siguiente para no saturarte.</div>`;
            });
        }

        if (aiReviewBtn) {
            aiReviewBtn.addEventListener('click', function () {
                const issues = [];

                if (accountIdInput && !String(accountIdInput.value || '').trim()) {
                    issues.push('Falta la cuenta.');
                }

                if (periodInput && !String(periodInput.value || '').trim()) {
                    issues.push('Falta el periodo.');
                }

                if (manualAmountInput && !String(manualAmountInput.value || '').trim()) {
                    issues.push('Falta el monto.');
                }

                if (emisorIdInput && !String(emisorIdInput.value || '').trim()) {
                    issues.push('Falta seleccionar emisor.');
                }

                if (receptorIdInput && !String(receptorIdInput.value || '').trim()) {
                    issues.push('Falta seleccionar receptor.');
                }

                if (!optionValue(metodoPagoSelect)) {
                    issues.push('Falta método de pago.');
                }

                if (!optionValue(monedaSelect)) {
                    issues.push('Falta moneda.');
                }

                if (getSelectedFilesCount() === 0) {
                    issues.push('Debes adjuntar al menos PDF o XML.');
                }

                if (!aiResponseBox) return;

                if (issues.length) {
                    aiResponseBox.innerHTML = '<div><strong>IA:</strong> Revisé el formulario y encontré:</div><div>• ' + issues.join('<br>• ') + '</div>';
                } else {
                    aiResponseBox.innerHTML = '<div><strong>IA:</strong> La captura base se ve consistente para continuar o generar la factura.</div>';
                }
            });
        }
    }

    function bindTipoButtons() {
        qsa('.invx-typebtn').forEach((btn) => {
            btn.addEventListener('click', function () {
                const value = this.getAttribute('data-value') || 'I';
                if (tipoComprobanteSelect) {
                    tipoComprobanteSelect.value = value;
                    tipoComprobanteSelect.dispatchEvent(new Event('change', { bubbles: true }));
                }
            });
        });

        if (tipoComprobanteSelect) {
            tipoComprobanteSelect.addEventListener('change', function () {
                applyTipoVisualState();
                syncWizardSummary();
                syncPpdUiState();
                syncAiHints();
            });
        }
    }

    function bindWizardWatchers() {
        [
            accountIdInput,
            periodInput,
            manualAmountInput,
            manualSerieInput,
            manualFolioInput,
            manualIssuedAtInput,
            manualIssuedDateInput,
            manualSourceInput,
            manualUuidInput,
            usoCfdiSelect,
            regimenSelect,
            formaPagoSelect,
            metodoPagoSelect,
            monedaSelect,
            exportacionSelect,
            objetoImpuestoSelect,
            complementoSelect,
            tipoComprobanteSelect,
            qs('#manual_status'),
            qs('#manual_pdf'),
            qs('#manual_xml')
        ].filter(Boolean).forEach((el) => {
            el.addEventListener('input', function () {
                syncWizardSummary();
                syncPpdUiState();
                syncAiHints();
            });

            el.addEventListener('change', function () {
                syncWizardSummary();
                syncPpdUiState();
                syncAiHints();
            });
        });

        const wizardObserverTarget = qs('#invxAdvancedManualForm');
        if (wizardObserverTarget) {
            const observer = new MutationObserver(function () {
                syncCurrentStepMemory();
                syncAiHints();
            });
            observer.observe(wizardObserverTarget, { subtree: true, attributes: true, attributeFilter: ['class'] });
        }
    }

    function bindAssistButtons() {
        if (openSingleAIAssist) {
            openSingleAIAssist.addEventListener('click', function () {
                openDrawer('singleDrawer');
                setTimeout(function () {
                    fillCurrentPeriod();
                    fillTodayFields();
                    fillDefaultSource();
                    if (emisorSearchInput) emisorSearchInput.focus();
                }, 100);
            });
        }

        if (assistToday) assistToday.addEventListener('click', () => fillTodayFields(true));
        if (assistCurrentPeriod) assistCurrentPeriod.addEventListener('click', () => fillCurrentPeriod(true));
        if (assistDefaultSource) assistDefaultSource.addEventListener('click', () => fillDefaultSource(true));
        if (assistClearUuid) assistClearUuid.addEventListener('click', clearUuid);

        if (assistFocusEmisor) {
            assistFocusEmisor.addEventListener('click', function () {
                if (emisorSearchInput) emisorSearchInput.focus();
                if (aiResponseBox) {
                    aiResponseBox.innerHTML = '<div><strong>IA:</strong> Te llevé al campo de búsqueda de emisor.</div>';
                }
            });
        }

        if (assistFocusReceptor) {
            assistFocusReceptor.addEventListener('click', function () {
                if (receptorSearchInput) receptorSearchInput.focus();
                if (aiResponseBox) {
                    aiResponseBox.innerHTML = '<div><strong>IA:</strong> Te llevé al campo de búsqueda de receptor.</div>';
                }
            });
        }
    }

    qsa('.invx-js-fill-current-period').forEach((btn) => {
        btn.addEventListener('click', function () {
            fillCurrentPeriod(true);
        });
    });

    qsa('.invx-js-set-filter').forEach((btn) => {
        btn.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const value = this.getAttribute('data-value') || '';
            const el = document.getElementById(targetId);
            if (el) {
                el.value = value;
                el.classList.add('invx-has-value');
            }
        });
    });

    qsa('.invx-js-smart-search').forEach((btn) => {
        btn.addEventListener('click', function () {
            const type = this.getAttribute('data-search-type') || '';
            if (!globalSearchInput) return;

            globalSearchInput.focus();

            if (!globalSearchInput.value.trim()) {
                if (type === 'uuid') {
                    globalSearchInput.placeholder = ' ';
                    globalSearchInput.setAttribute('data-hint', 'Pega aquí el UUID completo...');
                } else if (type === 'rfc') {
                    globalSearchInput.placeholder = ' ';
                    globalSearchInput.setAttribute('data-hint', 'Escribe RFC, cliente o razón social...');
                }
            }
        });
    });

    function bootstrapDefaults() {
        if (periodInput && !periodInput.value) {
            const now = new Date();
            const ym = now.getFullYear() + '-' + String(now.getMonth() + 1).padStart(2, '0');
            periodInput.value = ym;
        }

        if (cloneModeHidden && !cloneModeHidden.value) {
            cloneModeHidden.value = 'skip';
        }

        applyTipoVisualState();
        setCloneMode(cloneModeHidden ? cloneModeHidden.value : 'skip');
    }

    function init() {
        loadSeedCatalogs();
        bindCloneModeButtons();
        bindAiButtons();
        bindTipoButtons();
        bindWizardWatchers();
        bindAssistButtons();
        bootstrapDefaults();
        syncSelection();
        syncFloatingStates();
        syncWizardSummary();
        syncPpdUiState();
        syncAiHints();
    }

    init();
})();