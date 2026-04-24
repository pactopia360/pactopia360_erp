/* public/assets/client/js/pages/facturacion-create-360.js */

(function () {
    'use strict';

    const config = window.P360_FACTURACION_CREATE || {};
    const productos = Array.isArray(config.productos) ? config.productos : [];

    const root = document.querySelector('[data-fc360-create]');
    const form = document.getElementById('fc360CreateForm');
    const tbody = document.getElementById('fc360ConceptBody');

    if (!root || !form || !tbody) {
        return;
    }

    const money = new Intl.NumberFormat('es-MX', {
        style: 'currency',
        currency: 'MXN',
        minimumFractionDigits: 2,
    });

    const subtotalLabel = document.getElementById('fc360Subtotal');
    const ivaLabel = document.getElementById('fc360Iva');
    const totalLabel = document.getElementById('fc360Total');

    const metodoPago = document.getElementById('metodo_pago');
    const formaPago = document.getElementById('forma_pago');
    const ppdNotice = document.getElementById('fc360PpdNotice');
    const repStatus = document.getElementById('fc360RepStatus');
    const repText = document.getElementById('fc360RepText');

    const emisor = document.getElementById('cliente_id');
    const receptor = document.getElementById('receptor_id');

    const checkEmisor = document.querySelector('[data-check-emisor]');
    const checkReceptor = document.querySelector('[data-check-receptor]');
    const checkConceptos = document.querySelector('[data-check-conceptos]');
    const checkPpd = document.querySelector('[data-check-ppd]');

    const addBtn = document.querySelector('[data-add-concept]');
    const previewBtn = document.querySelector('[data-preview-btn]');

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
                </td>

                <td>
                    <textarea name="conceptos[${index}][descripcion]" rows="2" required placeholder="Descripción del concepto"></textarea>
                </td>

                <td>
                    <input name="conceptos[${index}][cantidad]" type="number" min="0.0001" step="0.0001" value="1" required data-cantidad>
                </td>

                <td>
                    <input name="conceptos[${index}][precio_unitario]" type="number" min="0" step="0.0001" value="0" required data-precio>
                </td>

                <td>
                    <select name="conceptos[${index}][iva_tasa]" data-iva>
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

    function getProductoById(id) {
        return productos.find((producto) => String(producto.id) === String(id));
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
        let ivaGlobal = 0;
        let hasValidConcept = false;

        const rows = tbody.querySelectorAll('[data-concept-row]');

        rows.forEach((row) => {
            const cantidad = toNumber(row.querySelector('[data-cantidad]')?.value);
            const precio = toNumber(row.querySelector('[data-precio]')?.value);
            const ivaTasa = toNumber(row.querySelector('[data-iva]')?.value);

            const subtotal = cantidad * precio;
            const iva = subtotal * ivaTasa;
            const total = subtotal + iva;

            subtotalGlobal += subtotal;
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
        if (ivaLabel) ivaLabel.textContent = format(ivaGlobal);
        if (totalLabel) totalLabel.textContent = format(subtotalGlobal + ivaGlobal);

        setCheck(checkEmisor, Boolean(emisor?.value));
        setCheck(checkReceptor, Boolean(receptor?.value));
        setCheck(checkConceptos, hasValidConcept);
        setCheck(checkPpd, Boolean(metodoPago?.value));

        updatePpdState();
    }

    function updatePpdState() {
        const isPpd = metodoPago && metodoPago.value === 'PPD';

        if (formaPago && isPpd) {
            formaPago.value = '99';
        }

        if (ppdNotice) {
            ppdNotice.classList.toggle('is-active', isPpd);
        }

        if (repStatus) {
            repStatus.textContent = isPpd ? 'REP requerido después del pago' : 'No requerido por ahora';
        }

        if (repText) {
            repText.textContent = isPpd
                ? 'Esta factura quedará marcada para registrar pagos, parcialidades, saldo insoluto y generar Complemento de Pago REP 2.0.'
                : 'Si eliges PPD, este CFDI quedará listo para registrar pagos y generar REP.';
        }
    }

    function addConceptRow() {
        const index = tbody.querySelectorAll('[data-concept-row]').length;
        tbody.insertAdjacentHTML('beforeend', rowTemplate(index));
        calculate();

        const lastRow = tbody.querySelector('[data-concept-row]:last-child');
        lastRow?.querySelector('textarea')?.focus();
    }

    function removeConceptRow(button) {
        const rows = tbody.querySelectorAll('[data-concept-row]');

        if (rows.length <= 1) {
            const row = rows[0];

            row.querySelectorAll('input, textarea').forEach((field) => {
                if (field.matches('[data-cantidad]')) {
                    field.value = '1';
                } else if (field.matches('[data-precio]')) {
                    field.value = '0';
                } else {
                    field.value = '';
                }
            });

            row.querySelectorAll('select').forEach((field) => {
                field.selectedIndex = 0;
            });

            calculate();
            return;
        }

        button.closest('[data-concept-row]')?.remove();
        reindexRows();
        calculate();
    }

    function preview() {
        const subtotal = subtotalLabel?.textContent || '$0.00';
        const iva = ivaLabel?.textContent || '$0.00';
        const total = totalLabel?.textContent || '$0.00';
        const metodo = metodoPago?.value || 'PUE';

        alert(
            [
                'Vista previa CFDI',
                '',
                `Método de pago: ${metodo}`,
                `Subtotal: ${subtotal}`,
                `IVA: ${iva}`,
                `Total: ${total}`,
                '',
                'Esta es una vista previa operativa. El timbrado real se conectará después con PAC/timbres.',
            ].join('\n')
        );
    }

    function validateBeforeSubmit(event) {
        const rows = tbody.querySelectorAll('[data-concept-row]');
        let hasConcept = false;

        rows.forEach((row) => {
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

        calculate();
    }

    tbody.addEventListener('input', function (event) {
        if (
            event.target.matches('[data-cantidad]') ||
            event.target.matches('[data-precio]') ||
            event.target.matches('textarea')
        ) {
            calculate();
        }
    });

    tbody.addEventListener('change', function (event) {
        const row = event.target.closest('[data-concept-row]');

        if (event.target.matches('[data-product-select]') && row) {
            fillProduct(row);
        }

        calculate();
    });

    tbody.addEventListener('click', function (event) {
        const button = event.target.closest('[data-remove-concept]');

        if (button) {
            removeConceptRow(button);
        }
    });

    addBtn?.addEventListener('click', addConceptRow);
    previewBtn?.addEventListener('click', preview);

    metodoPago?.addEventListener('change', calculate);
    formaPago?.addEventListener('change', calculate);
    emisor?.addEventListener('change', calculate);
    receptor?.addEventListener('change', calculate);

    form.addEventListener('submit', validateBeforeSubmit);

    calculate();
})();