/* public/assets/client/js/pages/facturacion-create-360.js */

(function () {
    'use strict';

    const config = window.P360_FACTURACION_CREATE || {};
    const productos = Array.isArray(config.productos) ? config.productos : [];
    let receptores = Array.isArray(config.receptores) ? config.receptores : [];
    const catalogs = config.catalogs || {};

    const root = document.querySelector('[data-fc360-create]');
    const form = document.getElementById('fc360CreateForm');
    const tbody = document.getElementById('fc360ConceptBody');

    if (!root || !form || !tbody) return;

    const money = new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
    });

    const subtotalLabel = document.getElementById('fc360Subtotal');
    const descuentoLabel = document.getElementById('fc360Descuento');
    const ivaLabel = document.getElementById('fc360Iva');
    const totalLabel = document.getElementById('fc360Total');

    const aiTitle = document.getElementById('fc360AiTitle');
    const aiMessage = document.getElementById('fc360AiMessage');
    const aiScore = document.getElementById('fc360AiScore');
    const aiHeroStatus = document.getElementById('fc360AiHeroStatus');
    const aiHeroText = document.getElementById('fc360AiHeroText');
    const aiPanel = document.querySelector('[data-ai-panel]');

    const tipoDocumento = document.getElementById('tipo_documento');
    const metodoPago = document.getElementById('metodo_pago');
    const formaPago = document.getElementById('forma_pago');
    const usoCfdi = document.getElementById('uso_cfdi');
    const regimenReceptor = document.getElementById('regimen_receptor');
    const cpReceptor = document.getElementById('cp_receptor');
    const moneda = document.getElementById('moneda');
    const tipoCambio = document.getElementById('tipo_cambio');
    const tipoRelacion = document.getElementById('tipo_relacion');
    const uuidRelacionado = document.getElementById('uuid_relacionado');

    const ppdNotice = document.getElementById('fc360PpdNotice');
    const repStatus = document.getElementById('fc360RepStatus');
    const repText = document.getElementById('fc360RepText');

    const emisor = document.getElementById('cliente_id');
    const receptor = document.getElementById('receptor_id');
    const receptorCard = document.querySelector('[data-receptor-card]');

    const checkEmisor = document.querySelector('[data-check-emisor]');
    const checkReceptor = document.querySelector('[data-check-receptor]');
    const checkRegimen = document.querySelector('[data-check-regimen]');
    const checkCp = document.querySelector('[data-check-cp]');
    const checkConceptos = document.querySelector('[data-check-conceptos]');
    const checkPpd = document.querySelector('[data-check-ppd]');

    const addBtn = document.querySelector('[data-add-concept]');
    const previewBtn = document.querySelector('[data-preview-btn]');

    const receptorNewBtn = document.querySelector('[data-receptor-new]');
    const receptorEditBtn = document.querySelector('[data-receptor-edit]');
    const receptorModal = document.querySelector('[data-receptor-modal]');
    const receptorForm = document.getElementById('fc360ReceptorForm');
    const receptorSaveBtn = document.querySelector('[data-receptor-save]');
    const modalAiHelp = document.querySelector('[data-modal-ai-help]');

    const modalRfc = document.getElementById('receptor_modal_rfc');
    const modalRegimen = document.getElementById('receptor_modal_regimen_fiscal');
    const modalCp = document.getElementById('receptor_modal_codigo_postal');
    const modalPais = document.getElementById('receptor_modal_pais');
    const modalEstado = document.getElementById('receptor_modal_estado');
    const modalMunicipio = document.getElementById('receptor_modal_municipio');
    const modalColonia = document.getElementById('receptor_modal_colonia');
    const modalUso = document.getElementById('receptor_modal_uso_cfdi');
    const modalMetodo = document.getElementById('receptor_modal_metodo_pago');
    const modalForma = document.getElementById('receptor_modal_forma_pago');

    let aiTimer = null;
    let cpTimer = null;
    let lastAssistant = null;
    let locationsLoaded = false;

    function toNumber(value) {
        const number = Number.parseFloat(value);
        return Number.isFinite(number) ? number : 0;
    }

    function format(value) {
        return money.format(toNumber(value));
    }

    function escapeHtml(value) {
        return String(value || '')
            .replaceAll('&', '&amp;')
            .replaceAll('<', '&lt;')
            .replaceAll('>', '&gt;')
            .replaceAll('"', '&quot;')
            .replaceAll("'", '&#039;');
    }

    function setCheck(element, active) {
        if (!element) return;
        element.classList.toggle('is-ok', Boolean(active));
    }

    function normalizeRfc(value) {
        return String(value || '')
            .toUpperCase()
            .replace(/[^A-ZÑ&0-9]/g, '')
            .slice(0, 13);
    }

    function personaByRfc(rfc) {
        const clean = normalizeRfc(rfc);
        if (clean.length === 12) return 'moral';
        if (clean.length === 13) return 'fisica';
        return 'desconocida';
    }

    function labelFromCatalog(catalogName, code) {
        const catalog = catalogs[catalogName] || {};
        return catalog[code] ? `${code} · ${catalog[code]}` : code || '';
    }

    function getReceptorById(id) {
        return receptores.find((item) => String(item.id) === String(id));
    }

    function getProductoById(id) {
        return productos.find((producto) => String(producto.id) === String(id));
    }

    function setValue(selector, value) {
        const field = document.querySelector(selector);
        if (field) field.value = value || '';
    }

    function setSelectValue(select, value) {
        if (!select || value === null || value === undefined || value === '') return;

        const exists = Array.from(select.options).some((option) => option.value === String(value));

        if (exists) {
            select.value = String(value);
            return;
        }

        const option = document.createElement('option');
        option.value = String(value);
        option.textContent = String(value);
        select.appendChild(option);
        select.value = String(value);
    }

    function resetSelect(select, placeholder) {
        if (!select) return;
        select.innerHTML = '';
        const option = document.createElement('option');
        option.value = '';
        option.textContent = placeholder;
        select.appendChild(option);
    }

    function appendOptions(select, items, selected = '') {
        if (!select || !Array.isArray(items)) return;

        items.forEach((item) => {
            const value = typeof item === 'string' ? item : (item.value || item.name || item.code || item.estado || item.municipio || item.colonia || '');
            const label = typeof item === 'string' ? item : (item.label || item.name || item.text || value);

            if (!value) return;

            const option = document.createElement('option');
            option.value = value;
            option.textContent = label;
            select.appendChild(option);
        });

        if (selected) setSelectValue(select, selected);
    }

    async function fetchJson(url) {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        const json = await response.json().catch(() => ({}));

        if (!response.ok || json.ok === false) {
            throw new Error(json.message || 'No se pudo cargar la información.');
        }

        return json;
    }

    async function loadLocationCatalogs() {
        if (locationsLoaded) return;
        locationsLoaded = true;

        try {
            if (config.routes?.countries && modalPais) {
                const countries = await fetchJson(config.routes.countries);
                resetSelect(modalPais, 'Selecciona país');
                appendOptions(
                    modalPais,
                    (countries.countries || []).map((country) => ({
                        value: country.code,
                        label: `${country.code} · ${country.name}`,
                    })),
                    'MEX'
                );
            }

            await loadStates();
        } catch (error) {
            updateModalAiHelp('No se pudieron cargar los catálogos de ubicación. Puedes continuar con CP.', 'warning');
        }
    }

    async function loadStates(selected = '') {
        if (!config.routes?.states || !modalEstado) return;

        const json = await fetchJson(config.routes.states);

        resetSelect(modalEstado, 'Selecciona estado');
        appendOptions(modalEstado, json.states || [], selected);

        resetSelect(modalMunicipio, 'Selecciona municipio');
        fillColonias([]);
    }

    async function loadMunicipalities(estado, selected = '') {
        if (!config.routes?.municipalities || !modalMunicipio || !estado) return;

        const url = `${config.routes.municipalities}?estado=${encodeURIComponent(estado)}`;
        const json = await fetchJson(url);

        resetSelect(modalMunicipio, 'Selecciona municipio');
        appendOptions(modalMunicipio, json.municipalities || [], selected);

        fillColonias([]);
    }

    async function loadColoniesByLocation(selected = '') {
        if (!config.routes?.colonies || !modalColonia) return;

        const params = new URLSearchParams();

        if (modalCp?.value) params.set('cp', modalCp.value);
        if (modalEstado?.value) params.set('estado', modalEstado.value);
        if (modalMunicipio?.value) params.set('municipio', modalMunicipio.value);

        if (!params.toString()) {
            fillColonias([]);
            return;
        }

        const json = await fetchJson(`${config.routes.colonies}?${params.toString()}`);
        fillColonias(json.colonies || json.items || [], selected);
    }

    function refreshReceptorOption(item) {
        if (!receptor || !item || !item.id) return;

        const label = item.label || `${item.razon_social || item.nombre_comercial || 'Receptor'} · ${item.rfc || ''}`;
        let option = Array.from(receptor.options).find((opt) => String(opt.value) === String(item.id));

        if (!option) {
            option = document.createElement('option');
            option.value = item.id;
            receptor.appendChild(option);
        }

        option.textContent = label;
        receptor.value = String(item.id);
    }

    function upsertReceptor(item) {
        if (!item || !item.id) return;

        const index = receptores.findIndex((current) => String(current.id) === String(item.id));

        if (index >= 0) {
            receptores[index] = item;
        } else {
            receptores.push(item);
        }

        refreshReceptorOption(item);
        applyReceptor(item.id);
    }

    function renderReceptorCard(item) {
        if (!receptorCard) return;

        if (!item) {
            receptorCard.innerHTML = `
                <strong>Sin receptor seleccionado</strong>
                <span>Selecciona o agrega un receptor para validar datos fiscales.</span>
            `;
            return;
        }

        const direccion = [
            item.calle,
            item.no_ext,
            item.no_int ? `Int. ${item.no_int}` : '',
            item.colonia,
            item.municipio,
            item.estado,
            item.codigo_postal,
        ].filter(Boolean).join(', ');

        const assistant = item.assistant || {};
        const nivel = assistant.nivel || 'pendiente';
        const score = assistant.score ?? '--';
        const warnings = Array.isArray(assistant.warnings) ? assistant.warnings.length : 0;

        receptorCard.innerHTML = `
            <strong>${escapeHtml(item.razon_social || item.nombre_comercial || item.rfc || 'Receptor')}</strong>
            <span>${escapeHtml(item.rfc || 'RFC sin capturar')}</span>
            <small>${escapeHtml([
                item.regimen_fiscal ? labelFromCatalog('regimenes_fiscales', item.regimen_fiscal) : '',
                item.uso_cfdi ? labelFromCatalog('usos_cfdi', item.uso_cfdi) : '',
                item.codigo_postal ? `CP ${item.codigo_postal}` : '',
            ].filter(Boolean).join(' · ') || 'Datos fiscales incompletos')}</small>
            ${direccion ? `<small>${escapeHtml(direccion)}</small>` : ''}
            <em class="fc360-ai-mini">IA fiscal: ${escapeHtml(nivel)} · score ${escapeHtml(score)}${warnings ? ` · ${warnings} alerta(s)` : ''}</em>
        `;
    }

    function applyReceptor(id) {
        const item = getReceptorById(id);

        if (!item) {
            renderReceptorCard(null);
            calculate();
            scheduleAssistant();
            return;
        }

        setSelectValue(usoCfdi, item.uso_cfdi || 'G03');
        setSelectValue(regimenReceptor, item.regimen_fiscal || '');
        if (cpReceptor) cpReceptor.value = item.codigo_postal || '';

        setSelectValue(metodoPago, item.metodo_pago || '');
        setSelectValue(formaPago, item.forma_pago || '');

        renderReceptorCard(item);
        calculate();
        scheduleAssistant();
    }

    function productOptionsHtml() {
        let html = '<option value="">Manual</option>';

        productos.forEach((producto) => {
            const id = producto.id ?? '';
            const label = producto.label || producto.descripcion || `Producto #${id}`;
            html += `<option value="${escapeHtml(id)}">${escapeHtml(label)}</option>`;
        });

        return html;
    }

    function rowTemplate(index) {
        return `
            <tr data-concept-row>
                <td>
                    <select name="conceptos[${index}][producto_id]" data-product-select>
                        ${productOptionsHtml()}
                    </select>
                    <input type="hidden" name="conceptos[${index}][clave_producto_sat]" value="">
                    <input type="hidden" name="conceptos[${index}][clave_unidad_sat]" value="">
                    <input type="hidden" name="conceptos[${index}][objeto_impuesto]" value="02">
                </td>

                <td>
                    <textarea name="conceptos[${index}][descripcion]" rows="2" required placeholder="Descripción del concepto" data-ai-watch></textarea>
                </td>

                <td>
                    <input name="conceptos[${index}][cantidad]" type="number" min="0.0001" step="0.0001" value="1" required data-cantidad data-ai-watch>
                </td>

                <td>
                    <input name="conceptos[${index}][precio_unitario]" type="number" min="0" step="0.0001" value="0" required data-precio data-ai-watch>
                </td>

                <td>
                    <input name="conceptos[${index}][descuento]" type="number" min="0" step="0.0001" value="0" data-descuento data-ai-watch>
                </td>

                <td>
                    <select name="conceptos[${index}][iva_tasa]" data-iva data-ai-watch>
                        <option value="0.16" selected>16%</option>
                        <option value="0.08">8%</option>
                        <option value="0">0%</option>
                    </select>
                </td>

                <td data-subtotal>$0.00</td>
                <td data-total>$0.00</td>

                <td>
                    <button type="button" class="fc360-iconbtn" data-remove-concept title="Eliminar concepto">×</button>
                </td>
            </tr>
        `;
    }

    function reindexRows() {
        const rows = tbody.querySelectorAll('[data-concept-row]');

        rows.forEach((row, index) => {
            row.querySelectorAll('select, input, textarea').forEach((field) => {
                const name = field.getAttribute('name');
                if (!name) return;
                field.setAttribute('name', name.replace(/conceptos\[\d+]/, `conceptos[${index}]`));
            });
        });
    }

    function fillProduct(row) {
        const productSelect = row.querySelector('[data-product-select]');
        const descripcion = row.querySelector('textarea[name*="[descripcion]"]');
        const precio = row.querySelector('[data-precio]');
        const iva = row.querySelector('[data-iva]');

        if (!productSelect || !productSelect.value) return;

        const producto = getProductoById(productSelect.value);
        if (!producto) return;

        if (descripcion && !descripcion.value.trim()) {
            descripcion.value = producto.descripcion || producto.label || '';
        }

        if (precio && toNumber(precio.value) <= 0) {
            precio.value = toNumber(producto.precio_unitario).toFixed(4);
        }

        if (iva && producto.iva_tasa !== null && producto.iva_tasa !== undefined) {
            iva.value = String(producto.iva_tasa);
        }
    }

    function calculate() {
        let subtotalGlobal = 0;
        let descuentoGlobal = 0;
        let ivaGlobal = 0;
        let hasValidConcept = false;

        tbody.querySelectorAll('[data-concept-row]').forEach((row) => {
            const cantidad = toNumber(row.querySelector('[data-cantidad]')?.value);
            const precio = toNumber(row.querySelector('[data-precio]')?.value);
            const descuento = toNumber(row.querySelector('[data-descuento]')?.value);
            const ivaTasa = toNumber(row.querySelector('[data-iva]')?.value);

            const subtotal = cantidad * precio;
            const descuentoAplicado = Math.min(subtotal, descuento);
            const base = Math.max(0, subtotal - descuentoAplicado);
            const iva = base * ivaTasa;
            const total = base + iva;

            subtotalGlobal += subtotal;
            descuentoGlobal += descuentoAplicado;
            ivaGlobal += iva;

            if (cantidad > 0 && precio >= 0 && row.querySelector('textarea[name*="[descripcion]"]')?.value.trim()) {
                hasValidConcept = true;
            }

            const subtotalCell = row.querySelector('[data-subtotal]');
            const totalCell = row.querySelector('[data-total]');

            if (subtotalCell) subtotalCell.textContent = format(subtotal);
            if (totalCell) totalCell.textContent = format(total);
        });

        if (subtotalLabel) subtotalLabel.textContent = format(subtotalGlobal);
        if (descuentoLabel) descuentoLabel.textContent = format(descuentoGlobal);
        if (ivaLabel) ivaLabel.textContent = format(ivaGlobal);
        if (totalLabel) totalLabel.textContent = format(subtotalGlobal - descuentoGlobal + ivaGlobal);

        setCheck(checkEmisor, Boolean(emisor?.value));
        setCheck(checkReceptor, Boolean(receptor?.value));
        setCheck(checkRegimen, Boolean(regimenReceptor?.value));
        setCheck(checkCp, /^\d{5}$/.test(cpReceptor?.value || ''));
        setCheck(checkConceptos, hasValidConcept);
        setCheck(checkPpd, Boolean(metodoPago?.value));

        updatePpdState();
    }

    function updatePpdState() {
        const isPpd = metodoPago && metodoPago.value === 'PPD';
        const isPago = tipoDocumento && tipoDocumento.value === 'P';

        if (formaPago && isPpd) {
            formaPago.value = '99';
        }

        if (usoCfdi && isPago) {
            setSelectValue(usoCfdi, 'CP01');
        }

        if (ppdNotice) {
            ppdNotice.classList.toggle('is-active', isPpd || isPago);
        }

        if (repStatus) {
            repStatus.textContent = isPpd
                ? 'REP requerido después del pago'
                : (isPago ? 'CFDI tipo pago / REP' : 'No requerido por ahora');
        }

        if (repText) {
            repText.textContent = isPpd
                ? 'Esta factura quedará marcada para registrar pagos, parcialidades, saldo insoluto y generar Complemento de Pago REP 2.0.'
                : (isPago
                    ? 'Este tipo CFDI se usará para registrar pagos relacionados con facturas PPD.'
                    : 'Si eliges PPD, este CFDI quedará listo para registrar pagos y generar REP.');
        }
    }

    function addConceptRow() {
        const index = tbody.querySelectorAll('[data-concept-row]').length;
        tbody.insertAdjacentHTML('beforeend', rowTemplate(index));
        calculate();

        const lastRow = tbody.querySelector('[data-concept-row]:last-child');
        lastRow?.querySelector('textarea')?.focus();
        scheduleAssistant();
    }

    function removeConceptRow(button) {
        const rows = tbody.querySelectorAll('[data-concept-row]');

        if (rows.length <= 1) {
            const row = rows[0];

            row.querySelectorAll('input, textarea').forEach((field) => {
                if (field.matches('[data-cantidad]')) {
                    field.value = '1';
                } else if (field.matches('[data-precio], [data-descuento]')) {
                    field.value = '0';
                } else if (field.type !== 'hidden') {
                    field.value = '';
                }
            });

            row.querySelectorAll('select').forEach((field) => {
                field.selectedIndex = 0;
            });

            calculate();
            scheduleAssistant();
            return;
        }

        button.closest('[data-concept-row]')?.remove();
        reindexRows();
        calculate();
        scheduleAssistant();
    }

    function getCurrentTotalNumber() {
        let subtotalGlobal = 0;
        let descuentoGlobal = 0;
        let ivaGlobal = 0;

        tbody.querySelectorAll('[data-concept-row]').forEach((row) => {
            const cantidad = toNumber(row.querySelector('[data-cantidad]')?.value);
            const precio = toNumber(row.querySelector('[data-precio]')?.value);
            const descuento = toNumber(row.querySelector('[data-descuento]')?.value);
            const ivaTasa = toNumber(row.querySelector('[data-iva]')?.value);

            const subtotal = cantidad * precio;
            const descuentoAplicado = Math.min(subtotal, descuento);
            const base = Math.max(0, subtotal - descuentoAplicado);
            const iva = base * ivaTasa;

            subtotalGlobal += subtotal;
            descuentoGlobal += descuentoAplicado;
            ivaGlobal += iva;
        });

        return subtotalGlobal - descuentoGlobal + ivaGlobal;
    }

    function buildAssistantPayload(extra = {}) {
        const receptorItem = getReceptorById(receptor?.value);

        return {
            rfc: receptorItem?.rfc || modalRfc?.value || '',
            receptor_rfc: receptorItem?.rfc || '',
            regimen_fiscal: regimenReceptor?.value || modalRegimen?.value || receptorItem?.regimen_fiscal || '',
            regimen_receptor: regimenReceptor?.value || receptorItem?.regimen_fiscal || '',
            codigo_postal: cpReceptor?.value || modalCp?.value || receptorItem?.codigo_postal || '',
            cp_receptor: cpReceptor?.value || receptorItem?.codigo_postal || '',
            uso_cfdi: usoCfdi?.value || modalUso?.value || receptorItem?.uso_cfdi || '',
            metodo_pago: metodoPago?.value || modalMetodo?.value || receptorItem?.metodo_pago || '',
            forma_pago: formaPago?.value || modalForma?.value || receptorItem?.forma_pago || '',
            tipo_documento: tipoDocumento?.value || 'I',
            tipo_comprobante: tipoDocumento?.value || 'I',
            moneda: moneda?.value || 'MXN',
            tipo_cambio: tipoCambio?.value || '',
            tipo_relacion: tipoRelacion?.value || '',
            uuid_relacionado: uuidRelacionado?.value || '',
            total: getCurrentTotalNumber(),
            ...extra,
        };
    }

    async function callAssistant(extra = {}) {
        if (!config.routes?.assistant) return null;

        try {
            const response = await fetch(config.routes.assistant, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrf || '',
                },
                body: JSON.stringify(buildAssistantPayload(extra)),
            });

            const json = await response.json().catch(() => ({}));

            if (!response.ok || !json.ok) {
                return null;
            }

            lastAssistant = json.assistant || null;
            renderAssistant(lastAssistant);

            return lastAssistant;
        } catch (error) {
            return null;
        }
    }

    function scheduleAssistant() {
        clearTimeout(aiTimer);
        aiTimer = setTimeout(() => callAssistant(), 450);
    }

    function renderAssistant(assistant) {
        if (!assistant) return;

        const score = assistant.score ?? 100;
        const nivel = assistant.nivel || 'excelente';
        const errors = Array.isArray(assistant.errors) ? assistant.errors : [];
        const warnings = Array.isArray(assistant.warnings) ? assistant.warnings : [];
        const suggestions = Array.isArray(assistant.suggestions) ? assistant.suggestions : [];

        if (aiScore) aiScore.textContent = score;

        if (aiPanel) {
            aiPanel.classList.remove('is-good', 'is-warning', 'is-danger');
            aiPanel.classList.add(score >= 85 ? 'is-good' : (score >= 65 ? 'is-warning' : 'is-danger'));
        }

        if (aiHeroStatus) {
            aiHeroStatus.textContent = nivel.charAt(0).toUpperCase() + nivel.slice(1);
        }

        const mainMessage = errors[0] || warnings[0] || suggestions[0] || 'Todo luce bien para guardar el borrador fiscal.';

        if (aiTitle) {
            aiTitle.textContent = errors.length
                ? 'Corrección fiscal necesaria'
                : (warnings.length ? 'Revisión fiscal recomendada' : 'Asistente fiscal activo');
        }

        if (aiMessage) aiMessage.textContent = mainMessage;
        if (aiHeroText) aiHeroText.textContent = mainMessage;

        applyAssistantSmartDefaults(assistant);
    }

    function applyAssistantSmartDefaults(assistant) {
        const defaults = assistant?.smart_defaults || {};

        if (defaults.forma_pago_sugerida && formaPago) {
            setSelectValue(formaPago, defaults.forma_pago_sugerida);
        }

        if (tipoDocumento?.value === 'P' && usoCfdi) {
            setSelectValue(usoCfdi, 'CP01');
        }
    }

    function suggestRegimenByRfc(rfc, targetSelect) {
        const tipo = personaByRfc(rfc);

        if (!targetSelect || targetSelect.value) return;

        if (tipo === 'moral') {
            setSelectValue(targetSelect, '601');
            updateModalAiHelp('RFC de persona moral detectado. Se sugirió régimen 601; confirma si corresponde.');
        }

        if (tipo === 'fisica') {
            setSelectValue(targetSelect, '612');
            updateModalAiHelp('RFC de persona física detectado. Se sugirió régimen 612; confirma si corresponde o cambia a RESICO 626.');
        }
    }

    function updateModalAiHelp(message, mode = 'info') {
        if (!modalAiHelp) return;

        modalAiHelp.classList.remove('is-warning', 'is-good', 'is-danger');

        if (mode === 'warning') modalAiHelp.classList.add('is-warning');
        if (mode === 'good') modalAiHelp.classList.add('is-good');
        if (mode === 'danger') modalAiHelp.classList.add('is-danger');

        modalAiHelp.innerHTML = `
            <strong>Asistente fiscal</strong>
            <span>${escapeHtml(message)}</span>
        `;
    }

    async function lookupPostalCode(cp, target = 'modal') {
        const clean = String(cp || '').replace(/\D+/g, '').slice(0, 5);

        if (clean.length !== 5 || !config.routes?.postalCodeBase) return;

        try {
            const response = await fetch(`${config.routes.postalCodeBase}/${encodeURIComponent(clean)}`, {
                method: 'GET',
                headers: { Accept: 'application/json' },
            });

            const json = await response.json().catch(() => ({}));

            if (!response.ok || !json.ok) {
                updateModalAiHelp(json.message || 'No se pudo validar el código postal.', 'warning');
                return;
            }

            if (!json.found) {
                if (target === 'modal') {
                    resetSelect(modalEstado, 'Selecciona estado');
                    resetSelect(modalMunicipio, 'Selecciona municipio');
                    fillColonias([]);
                }

                updateModalAiHelp(json.message || 'CP sin catálogo SEPOMEX. Puedes seleccionar estado y municipio manualmente.', 'warning');
                return;
            }

            const colonias = normalizeColonias(json.colonias || json.items || []);

            if (target === 'modal') {
                await loadStates(json.estado || '');
                await loadMunicipalities(json.estado || '', json.municipio || '');
                fillColonias(colonias);

                updateModalAiHelp(
                    colonias.length > 1
                        ? `CP ${clean} localizado: ${json.municipio}, ${json.estado}. Selecciona una de las ${colonias.length} colonias disponibles.`
                        : `CP ${clean} localizado: ${json.municipio}, ${json.estado}.`,
                    'good'
                );
            }

            if (target === 'main') {
                const item = getReceptorById(receptor?.value);

                if (item) {
                    item.estado = json.estado || item.estado;
                    item.municipio = json.municipio || item.municipio;
                    renderReceptorCard(item);
                }
            }
        } catch (error) {
            updateModalAiHelp('No se pudo consultar el CP en este momento. Puedes seleccionar ubicación manualmente.', 'warning');
        }
    }

    function normalizeColonias(source) {
        if (!Array.isArray(source)) return [];

        return source
            .map((item) => {
                if (typeof item === 'string') return item;
                if (item && typeof item === 'object') return item.colonia || item.nombre || '';
                return '';
            })
            .map((value) => String(value || '').trim())
            .filter(Boolean)
            .filter((value, index, array) => array.indexOf(value) === index)
            .sort((a, b) => a.localeCompare(b, 'es-MX'));
    }

    function fillColonias(colonias, selected = '') {
        if (!modalColonia) return;

        const normalized = normalizeColonias(colonias);
        const current = selected || modalColonia.value || '';

        modalColonia.innerHTML = '';

        const placeholder = document.createElement('option');
        placeholder.value = '';
        placeholder.textContent = normalized.length ? 'Selecciona colonia' : 'Sin colonias disponibles';
        modalColonia.appendChild(placeholder);

        normalized.forEach((colonia) => {
            const option = document.createElement('option');
            option.value = colonia;
            option.textContent = colonia;
            modalColonia.appendChild(option);
        });

        if (current) {
            const exists = Array.from(modalColonia.options).some((option) => option.value === current);

            if (!exists) {
                const custom = document.createElement('option');
                custom.value = current;
                custom.textContent = current;
                modalColonia.appendChild(custom);
            }

            modalColonia.value = current;
        }

        if (!current && normalized.length === 1) {
            modalColonia.value = normalized[0];
        }
    }

    function preview() {
        calculate();

        const subtotal = subtotalLabel?.textContent || '$0.00';
        const descuento = descuentoLabel?.textContent || '$0.00';
        const iva = ivaLabel?.textContent || '$0.00';
        const total = totalLabel?.textContent || '$0.00';
        const metodo = metodoPago?.value || 'PUE';
        const receptorItem = getReceptorById(receptor?.value);
        const assistant = lastAssistant;

        const tips = [];
        if (assistant?.errors?.length) tips.push(`Correcciones: ${assistant.errors.join(' ')}`);
        if (assistant?.warnings?.length) tips.push(`Alertas: ${assistant.warnings.join(' ')}`);
        if (assistant?.suggestions?.length) tips.push(`Sugerencias: ${assistant.suggestions.join(' ')}`);

        alert(
            [
                'Vista previa inteligente CFDI',
                '',
                `Receptor: ${receptorItem?.razon_social || 'Sin receptor'}`,
                `RFC: ${receptorItem?.rfc || 'N/A'}`,
                `Tipo CFDI: ${tipoDocumento?.value || 'I'}`,
                `Método de pago: ${metodo}`,
                `Forma de pago: ${formaPago?.value || 'N/A'}`,
                `Subtotal: ${subtotal}`,
                `Descuento: ${descuento}`,
                `IVA: ${iva}`,
                `Total: ${total}`,
                '',
                `Score fiscal: ${assistant?.score ?? 'pendiente'}`,
                tips.length ? tips.join('\n') : 'Sin alertas fiscales por ahora.',
                '',
                'El timbrado real se conectará después con PAC/timbres.',
            ].join('\n')
        );
    }

    function validateBeforeSubmit(event) {
        calculate();

        let hasConcept = false;

        tbody.querySelectorAll('[data-concept-row]').forEach((row) => {
            const descripcion = row.querySelector('textarea[name*="[descripcion]"]')?.value.trim();
            const cantidad = toNumber(row.querySelector('[data-cantidad]')?.value);
            const precio = toNumber(row.querySelector('[data-precio]')?.value);

            if (descripcion && cantidad > 0 && precio >= 0) {
                hasConcept = true;
            }
        });

        if (!hasConcept) {
            event.preventDefault();
            alert('Agrega al menos un concepto válido antes de guardar.');
            return;
        }

        if (!emisor?.value) {
            event.preventDefault();
            alert('Selecciona el emisor del CFDI.');
            emisor?.focus();
            return;
        }

        if (!receptor?.value) {
            event.preventDefault();
            alert('Selecciona el receptor del CFDI.');
            receptor?.focus();
            return;
        }

        if (!regimenReceptor?.value) {
            event.preventDefault();
            alert('Selecciona el régimen fiscal del receptor.');
            regimenReceptor?.focus();
            return;
        }

        if (!/^\d{5}$/.test(cpReceptor?.value || '')) {
            event.preventDefault();
            alert('Captura un código postal fiscal válido de 5 dígitos.');
            cpReceptor?.focus();
            return;
        }

        if (metodoPago?.value === 'PPD' && formaPago?.value !== '99') {
            event.preventDefault();
            alert('Para método PPD, la forma de pago debe ser 99 Por definir.');
            formaPago?.focus();
            return;
        }

        if (tipoDocumento?.value === 'E' && !tipoRelacion?.value) {
            event.preventDefault();
            alert('Para nota de crédito/egreso, selecciona una relación CFDI.');
            tipoRelacion?.focus();
            return;
        }
    }

    async function openReceptorModal(mode, item) {
        if (!receptorModal || !receptorForm) return;

        receptorForm.reset();
        resetSelect(modalEstado, 'Selecciona estado');
        resetSelect(modalMunicipio, 'Selecciona municipio');
        fillColonias([]);

        await loadLocationCatalogs();

        const title = document.getElementById('fc360ReceptorModalTitle');

        if (title) {
            title.textContent = mode === 'edit' ? 'Editar receptor' : 'Agregar receptor';
        }

        setValue('#receptor_modal_id', item?.id || '');
        setValue('#receptor_modal_rfc', item?.rfc || '');
        setValue('#receptor_modal_razon_social', item?.razon_social || '');
        setValue('#receptor_modal_nombre_comercial', item?.nombre_comercial || '');
        setValue('#receptor_modal_email', item?.email || '');
        setValue('#receptor_modal_codigo_postal', item?.codigo_postal || '');
        setValue('#receptor_modal_telefono', item?.telefono || '');
        setSelectValue(modalPais, item?.pais || 'MEX');
        setValue('#receptor_modal_calle', item?.calle || '');
        setValue('#receptor_modal_no_ext', item?.no_ext || '');
        setValue('#receptor_modal_no_int', item?.no_int || '');

        setSelectValue(modalUso, item?.uso_cfdi || 'G03');
        setSelectValue(modalRegimen, item?.regimen_fiscal || '');
        setSelectValue(modalMetodo, item?.metodo_pago || '');
        setSelectValue(modalForma, item?.forma_pago || '');

        if (item?.estado) {
            await loadStates(item.estado);
            if (item?.municipio) {
                await loadMunicipalities(item.estado, item.municipio);
                await loadColoniesByLocation(item.colonia || '');
            }
        } else if (item?.codigo_postal) {
            await lookupPostalCode(item.codigo_postal, 'modal');
            fillColonias([item.colonia].filter(Boolean), item.colonia || '');
        }

        updateModalAiHelp(
            mode === 'edit'
                ? 'Edita los datos; el asistente validará RFC, régimen fiscal, ubicación y código postal.'
                : 'Captura RFC y código postal; Pactopia360 sugerirá régimen, tipo de persona y ubicación.'
        );

        receptorModal.classList.add('is-open');
        receptorModal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('fc360-modal-open');

        setTimeout(() => modalRfc?.focus(), 50);
    }

    function closeReceptorModal() {
        if (!receptorModal) return;

        receptorModal.classList.remove('is-open');
        receptorModal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('fc360-modal-open');
    }

    function formToJson(formElement) {
        const formData = new FormData(formElement);
        const payload = {};

        formData.forEach((value, key) => {
            payload[key] = typeof value === 'string' ? value.trim() : value;
        });

        payload.rfc = normalizeRfc(payload.rfc || '');
        payload.pais = String(payload.pais || 'MEX').toUpperCase();

        return payload;
    }

    async function saveReceptor(event) {
        event.preventDefault();

        if (!receptorForm) return;

        const payload = formToJson(receptorForm);
        const id = payload.id || '';
        const isEdit = Boolean(id);

        if (!payload.rfc || payload.rfc.length < 12) {
            alert('Captura un RFC válido del receptor.');
            modalRfc?.focus();
            return;
        }

        if (!payload.razon_social) {
            alert('Captura la razón social del receptor.');
            document.getElementById('receptor_modal_razon_social')?.focus();
            return;
        }

        if (!payload.regimen_fiscal) {
            alert('Selecciona el régimen fiscal del receptor.');
            modalRegimen?.focus();
            return;
        }

        if (!/^\d{5}$/.test(payload.codigo_postal || '')) {
            alert('Captura un código postal fiscal válido de 5 dígitos.');
            modalCp?.focus();
            return;
        }

        if (!payload.estado) {
            alert('Selecciona el estado del receptor.');
            modalEstado?.focus();
            return;
        }

        if (!payload.municipio) {
            alert('Selecciona el municipio del receptor.');
            modalMunicipio?.focus();
            return;
        }

        if (!payload.colonia) {
            alert('Selecciona la colonia del receptor.');
            modalColonia?.focus();
            return;
        }

        const url = isEdit
            ? `${config.routes.receptorUpdateBase}/${encodeURIComponent(id)}`
            : config.routes.receptorStore;

        receptorSaveBtn?.setAttribute('disabled', 'disabled');

        try {
            const response = await fetch(url, {
                method: isEdit ? 'PUT' : 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': config.csrf || '',
                },
                body: JSON.stringify(payload),
            });

            const json = await response.json().catch(() => ({}));

            if (!response.ok || !json.ok) {
                const errors = json.errors
                    ? Object.values(json.errors).flat().join('\n')
                    : (json.message || 'No se pudo guardar el receptor.');

                throw new Error(errors);
            }

            upsertReceptor(json.receptor);
            closeReceptorModal();
            scheduleAssistant();
        } catch (error) {
            alert(error.message || 'No se pudo guardar el receptor.');
        } finally {
            receptorSaveBtn?.removeAttribute('disabled');
        }
    }

    tbody.addEventListener('input', function (event) {
        if (
            event.target.matches('[data-cantidad]') ||
            event.target.matches('[data-precio]') ||
            event.target.matches('[data-descuento]') ||
            event.target.matches('textarea')
        ) {
            calculate();
            scheduleAssistant();
        }
    });

    tbody.addEventListener('change', function (event) {
        const row = event.target.closest('[data-concept-row]');

        if (event.target.matches('[data-product-select]') && row) {
            fillProduct(row);
        }

        calculate();
        scheduleAssistant();
    });

    tbody.addEventListener('click', function (event) {
        const button = event.target.closest('[data-remove-concept]');

        if (button) removeConceptRow(button);
    });

    addBtn?.addEventListener('click', addConceptRow);
    previewBtn?.addEventListener('click', preview);

    receptorNewBtn?.addEventListener('click', function () {
        openReceptorModal('new', null);
    });

    receptorEditBtn?.addEventListener('click', function () {
        const item = getReceptorById(receptor?.value);

        if (!item) {
            alert('Selecciona un receptor para editarlo.');
            receptor?.focus();
            return;
        }

        openReceptorModal('edit', item);
    });

    receptor?.addEventListener('change', function () {
        applyReceptor(receptor.value);
    });

    receptorForm?.addEventListener('submit', saveReceptor);

    document.querySelectorAll('[data-receptor-close]').forEach((btn) => {
        btn.addEventListener('click', closeReceptorModal);
    });

    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape' && receptorModal?.classList.contains('is-open')) {
            closeReceptorModal();
        }
    });

    modalRfc?.addEventListener('input', function () {
        modalRfc.value = normalizeRfc(modalRfc.value);
        suggestRegimenByRfc(modalRfc.value, modalRegimen);
    });

    modalCp?.addEventListener('input', function () {
        modalCp.value = String(modalCp.value || '').replace(/\D+/g, '').slice(0, 5);

        clearTimeout(cpTimer);
        cpTimer = setTimeout(() => lookupPostalCode(modalCp.value, 'modal'), 450);
    });

    modalEstado?.addEventListener('change', async function () {
        await loadMunicipalities(modalEstado.value);
        updateModalAiHelp('Estado seleccionado. Ahora elige municipio y colonia.', 'good');
    });

    modalMunicipio?.addEventListener('change', async function () {
        await loadColoniesByLocation();
        updateModalAiHelp('Municipio seleccionado. Ahora elige colonia.', 'good');
    });

    modalColonia?.addEventListener('change', function () {
        updateModalAiHelp('Colonia seleccionada. La dirección fiscal está lista para guardar.', 'good');
    });

    cpReceptor?.addEventListener('input', function () {
        cpReceptor.value = String(cpReceptor.value || '').replace(/\D+/g, '').slice(0, 5);

        clearTimeout(cpTimer);
        cpTimer = setTimeout(() => {
            lookupPostalCode(cpReceptor.value, 'main');
            scheduleAssistant();
        }, 450);
    });

    document.querySelectorAll('[data-ai-watch]').forEach((field) => {
        field.addEventListener('input', function () {
            calculate();
            scheduleAssistant();
        });

        field.addEventListener('change', function () {
            calculate();
            scheduleAssistant();
        });
    });

    document.querySelectorAll('[data-modal-ai-watch]').forEach((field) => {
        field.addEventListener('change', function () {
            callAssistant({
                rfc: modalRfc?.value || '',
                regimen_fiscal: modalRegimen?.value || '',
                codigo_postal: modalCp?.value || '',
                uso_cfdi: modalUso?.value || '',
                metodo_pago: modalMetodo?.value || '',
                forma_pago: modalForma?.value || '',
            });
        });
    });

    metodoPago?.addEventListener('change', function () {
        calculate();
        scheduleAssistant();
    });

    formaPago?.addEventListener('change', function () {
        calculate();
        scheduleAssistant();
    });

    tipoDocumento?.addEventListener('change', function () {
        calculate();
        scheduleAssistant();
    });

    emisor?.addEventListener('change', function () {
        calculate();
        scheduleAssistant();
    });

    form.addEventListener('submit', validateBeforeSubmit);

    if (receptor?.value) {
        applyReceptor(receptor.value);
    }

    calculate();
    scheduleAssistant();
})();