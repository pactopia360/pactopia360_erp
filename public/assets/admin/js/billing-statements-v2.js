// C:\wamp64\www\pactopia360_erp\public\assets\admin\js\billing-statements-v2.js

(function () {
    'use strict';

    const root = document;
    const page = root.querySelector('[data-bsv2-root]');

    if (!page) {
        return;
    }

    const body = document.body;
    const csrfToken = root.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

    const previewModal = root.getElementById('bsv2-preview-modal');
    const editModal = root.getElementById('bsv2-edit-modal');
    const emailModal = root.getElementById('bsv2-email-modal');
    const advanceModal = root.getElementById('bsv2-advance-modal');
    const bulkPaymentsModal = root.getElementById('bsv2-bulk-payments-modal');

    const previewIframe = root.getElementById('bsv2-preview-iframe');
    const previewPlaceholder = root.getElementById('bsv2-preview-placeholder');
    const previewTitle = root.getElementById('bsv2-preview-title');
    const previewSubtitle = root.getElementById('bsv2-preview-subtitle');
    const previewOpenTab = root.getElementById('bsv2-preview-open-tab');

    const editForm = root.getElementById('bsv2-edit-form');
    const editAccountId = root.getElementById('bsv2-edit-account-id');
    const editPeriod = root.getElementById('bsv2-edit-period');
    const editClientName = root.getElementById('bsv2-edit-client-name');
    const editPeriodLabel = root.getElementById('bsv2-edit-period-label');
    const editTotal = root.getElementById('bsv2-edit-total');
    const editStatus = root.getElementById('bsv2-edit-status');
    const editPaymentMethod = root.getElementById('bsv2-edit-payment-method');
    const editPaidAt = root.getElementById('bsv2-edit-paid-at');
    const editPaymentReference = root.getElementById('bsv2-edit-payment-reference');
    const editPaymentNotes = root.getElementById('bsv2-edit-payment-notes');
    const editSubtitle = root.getElementById('bsv2-edit-subtitle');

    const emailForm = root.getElementById('bsv2-email-form');
    const emailAccountId = root.getElementById('bsv2-email-account-id');
    const emailPeriod = root.getElementById('bsv2-email-period');
    const emailClientName = root.getElementById('bsv2-email-client-name');
    const emailPeriodLabel = root.getElementById('bsv2-email-period-label');
    const emailTo = root.getElementById('bsv2-email-to');
    const emailSubject = root.getElementById('bsv2-email-subject');
    const emailMessage = root.getElementById('bsv2-email-message');
    const emailSubtitle = root.getElementById('bsv2-email-subtitle');

    const filtersForm = root.getElementById('bsv2-filters-form');
    const selectedHiddenInputsContainer = root.getElementById('bsv2-selected-hidden-inputs');
    const selectedCountNode = root.getElementById('bsv2-selected-count');
    const masterCheckbox = root.getElementById('bsv2-master-checkbox');
    const rowCheckboxes = Array.from(root.querySelectorAll('.bsv2-row-checkbox'));

    const sendAllButton = root.getElementById('bsv2-send-all-email');
    const sendSelectedButton = root.getElementById('bsv2-send-selected-email');
    const selectAllVisibleButton = root.getElementById('bsv2-select-all-visible');
    const clearSelectedButton = root.getElementById('bsv2-clear-selected');
    const openAdvanceModalButton = root.getElementById('bsv2-open-advance-modal');
    const openBulkPaymentsModalButton = root.getElementById('bsv2-open-bulk-payments-modal');

    const advanceClientInput = root.getElementById('bsv2-advance-client');
    const advancePaymentDateInput = root.getElementById('bsv2-advance-payment-date');
    const advanceMethodInput = root.getElementById('bsv2-advance-method');
    const advanceReferenceInput = root.getElementById('bsv2-advance-reference');
    const advanceRows = root.getElementById('bsv2-advance-rows');
    const addAdvanceRowButton = root.getElementById('bsv2-add-advance-row');
    const confirmAdvancePaymentButton = root.getElementById('bsv2-confirm-advance-payment');

    const bulkPaymentsRows = root.getElementById('bsv2-bulk-payments-rows');
    const addBulkPaymentRowButton = root.getElementById('bsv2-add-bulk-payment-row');
    const confirmBulkPaymentsButton = root.getElementById('bsv2-confirm-bulk-payments');

    let activeModal = null;

    function safeText(value, fallback = '') {
        const text = (value || '').toString().trim();
        return text !== '' ? text : fallback;
    }

    function safeMoney(value) {
        const num = Number.parseFloat(value);
        if (Number.isNaN(num)) {
            return '$0.00';
        }

        return '$' + num.toLocaleString('es-MX', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    function normalizeStatusValue(status) {
        const value = safeText(status, 'pendiente').toLowerCase();

        if (value === 'paid') return 'pagado';
        if (value === 'pending') return 'pendiente';
        if (value === 'partial') return 'parcial';
        if (value === 'overdue' || value === 'late') return 'vencido';

        return value;
    }

    function parseDataset(button, key, fallback = '') {
        if (!button || !button.dataset) {
            return fallback;
        }

        const value = button.dataset[key];
        return typeof value === 'string' ? value : fallback;
    }

    function showMessage(message, type = 'success') {
        window.alert((type === 'error' ? 'Error: ' : '') + message);
    }

    function getRouteUrl(name) {
        const map = {
            bulkSend: page.dataset.bsv2BulkSendUrl || '',
            advancePayments: page.dataset.bsv2AdvancePaymentsUrl || '',
            bulkPayments: page.dataset.bsv2BulkPaymentsUrl || ''
        };

        return safeText(map[name], '');
    }

    function setupAccordion(buttonId, contentId, defaultExpanded = false) {
        const toggleButton = root.getElementById(buttonId);
        const content = root.getElementById(contentId);

        if (!toggleButton || !content) {
            return;
        }

        const setExpanded = (expanded) => {
            toggleButton.setAttribute('aria-expanded', expanded ? 'true' : 'false');
            content.hidden = !expanded;
        };

        setExpanded(defaultExpanded);

        toggleButton.addEventListener('click', function () {
            const isExpanded = toggleButton.getAttribute('aria-expanded') === 'true';
            setExpanded(!isExpanded);
        });
    }

    function getActionDropdowns() {
        return Array.from(root.querySelectorAll('.bsv2-actions-dropdown'));
    }

    function getActionToggleButtons() {
        return Array.from(root.querySelectorAll('[data-bsv2-toggle-actions]'));
    }

    function setExpanded(button, expanded) {
        if (!button) {
            return;
        }

        button.setAttribute('aria-expanded', expanded ? 'true' : 'false');
    }

    function closeDropdown(dropdown) {
    if (!dropdown) {
        return;
    }

    const button = dropdown.querySelector('[data-bsv2-toggle-actions]');
    const menu = dropdown.querySelector('.bsv2-actions-menu');

    dropdown.classList.remove('is-open');

    if (menu) {
        menu.style.removeProperty('--bsv2-menu-top');
        menu.style.removeProperty('--bsv2-menu-left');
    }

    setExpanded(button, false);
}

    function openDropdown(dropdown) {
    if (!dropdown) {
        return;
    }

    closeAllActionMenus(dropdown);

    const button = dropdown.querySelector('[data-bsv2-toggle-actions]');
    const menu = dropdown.querySelector('.bsv2-actions-menu');

    if (!button || !menu) {
        return;
    }

    const buttonRect = button.getBoundingClientRect();
    const viewportWidth = window.innerWidth || document.documentElement.clientWidth;
    const viewportHeight = window.innerHeight || document.documentElement.clientHeight;

    dropdown.classList.add('is-open');
    setExpanded(button, true);

    const menuRect = menu.getBoundingClientRect();
    const menuWidth = Math.max(menuRect.width || 220, 220);
    const menuHeight = Math.max(menuRect.height || 190, 190);
    const gap = 10;

    let left = buttonRect.left - menuWidth - gap;
    let top = buttonRect.top + (buttonRect.height / 2) - (menuHeight / 2);

    if (left < 12) {
        left = buttonRect.right + gap;
    }

    if (left + menuWidth > viewportWidth - 12) {
        left = Math.max(12, viewportWidth - menuWidth - 12);
    }

    if (top < 12) {
        top = 12;
    }

    if (top + menuHeight > viewportHeight - 12) {
        top = Math.max(12, viewportHeight - menuHeight - 12);
    }

    menu.style.setProperty('--bsv2-menu-top', top + 'px');
    menu.style.setProperty('--bsv2-menu-left', left + 'px');
}

    function closeAllActionMenus(exceptDropdown) {
        getActionDropdowns().forEach(function (dropdown) {
            if (exceptDropdown && dropdown === exceptDropdown) {
                return;
            }

            closeDropdown(dropdown);
        });
    }

    function repositionOpenDropdown() {
    const opened = getActionDropdowns().find(function (dropdown) {
        return dropdown.classList.contains('is-open');
    });

    if (!opened) {
        return;
    }

    openDropdown(opened);
}

    function bindActionButtons() {
        getActionToggleButtons().forEach(function (button) {
            if (button.dataset.bsv2Bound === '1') {
                return;
            }

            button.dataset.bsv2Bound = '1';

            button.addEventListener('click', function (event) {
                event.preventDefault();
                event.stopPropagation();

                const dropdown = button.closest('.bsv2-actions-dropdown');

                if (!dropdown) {
                    return;
                }

                if (dropdown.classList.contains('is-open')) {
                    closeDropdown(dropdown);
                    return;
                }

                openDropdown(dropdown);
            });
        });
    }

    function bindMenuClicks() {
        const menus = Array.from(root.querySelectorAll('.bsv2-actions-menu'));

        menus.forEach(function (menu) {
            if (menu.dataset.bsv2Bound === '1') {
                return;
            }

            menu.dataset.bsv2Bound = '1';

            menu.addEventListener('click', function (event) {
                const disabledItem = event.target.closest('.is-disabled');
                if (disabledItem) {
                    event.preventDefault();
                    event.stopPropagation();
                    return;
                }

                const clickedButton = event.target.closest('button');
                const clickedLink = event.target.closest('a');

                if (!clickedButton && !clickedLink) {
                    event.stopPropagation();
                    return;
                }

                const dropdown = menu.closest('.bsv2-actions-dropdown');

                if (clickedLink) {
                    closeDropdown(dropdown);
                    return;
                }

                if (clickedButton && clickedButton.type !== 'submit') {
                    closeDropdown(dropdown);
                }
            });
        });
    }

    function getFocusableElements(modal) {
        if (!modal) {
            return [];
        }

        return Array.from(
            modal.querySelectorAll(
                'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])'
            )
        ).filter(function (element) {
            return !element.hasAttribute('hidden') && element.offsetParent !== null;
        });
    }

    function lockScroll() {
        body.classList.add('bsv2-modal-open');
    }

    function unlockScroll() {
        body.classList.remove('bsv2-modal-open');
    }

    function openModal(modal, focusTarget) {
        if (!modal) {
            return;
        }

        closeAllActionMenus();

        if (activeModal && activeModal !== modal) {
            closeModal(activeModal, false);
        }

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        activeModal = modal;

        lockScroll();

        window.setTimeout(function () {
            if (focusTarget && typeof focusTarget.focus === 'function') {
                focusTarget.focus();
                return;
            }

            const focusables = getFocusableElements(modal);
            if (focusables.length > 0) {
                focusables[0].focus();
            }
        }, 30);
    }

    function resetPreviewModal() {
        if (previewIframe) previewIframe.setAttribute('src', 'about:blank');
        if (previewOpenTab) previewOpenTab.setAttribute('href', '#');
        if (previewTitle) previewTitle.textContent = 'Vista previa del estado de cuenta';
        if (previewSubtitle) previewSubtitle.textContent = 'Revisión rápida antes de descargar o enviar.';

        if (previewPlaceholder) {
            previewPlaceholder.textContent = 'Selecciona un estado de cuenta para ver su vista previa.';
            previewPlaceholder.classList.remove('is-hidden');
        }
    }

    function resetEditModal() {
        if (!editForm) return;

        editForm.setAttribute('action', '#');
        if (editAccountId) editAccountId.value = '';
        if (editPeriod) editPeriod.value = '';
        if (editClientName) editClientName.textContent = '—';
        if (editPeriodLabel) editPeriodLabel.textContent = '—';
        if (editTotal) editTotal.textContent = '$0.00';
        if (editStatus) editStatus.value = 'pendiente';
        if (editPaymentMethod) editPaymentMethod.value = 'manual';
        if (editPaidAt) editPaidAt.value = '';
        if (editPaymentReference) editPaymentReference.value = '';
        if (editPaymentNotes) editPaymentNotes.value = '';
        if (editSubtitle) editSubtitle.textContent = 'Actualiza estatus, datos de pago y control administrativo.';
    }

    function resetEmailModal() {
        if (!emailForm) return;

        emailForm.setAttribute('action', '#');
        if (emailAccountId) emailAccountId.value = '';
        if (emailPeriod) emailPeriod.value = '';
        if (emailClientName) emailClientName.textContent = '—';
        if (emailPeriodLabel) emailPeriodLabel.textContent = '—';
        if (emailTo) emailTo.value = '';
        if (emailSubject) emailSubject.value = '';
        if (emailMessage) emailMessage.value = '';
        if (emailSubtitle) emailSubtitle.textContent = 'Configura destinatarios, asunto y mensaje antes de enviar.';
    }

    function buildAdvanceRow(values = {}) {
        const wrapper = document.createElement('div');
        wrapper.className = 'bsv2-pay-line';

        wrapper.innerHTML = `
            <div class="bsv2-pay-line__grid">
                <div class="bsv2-field">
                    <label class="bsv2-label">Período</label>
                    <input type="month" class="bsv2-control" name="advance_period[]" value="${safeText(values.period, '')}">
                </div>

                <div class="bsv2-field">
                    <label class="bsv2-label">Tipo</label>
                    <select class="bsv2-control" name="advance_type[]">
                        <option value="full" ${safeText(values.type, 'full') === 'full' ? 'selected' : ''}>Pago completo</option>
                        <option value="partial" ${safeText(values.type, '') === 'partial' ? 'selected' : ''}>Pago parcial</option>
                    </select>
                </div>

                <div class="bsv2-field">
                    <label class="bsv2-label">Monto aplicado</label>
                    <input type="number" step="0.01" min="0" class="bsv2-control" name="advance_amount[]" value="${safeText(values.amount, '')}" placeholder="0.00">
                </div>

                <div class="bsv2-field">
                    <label class="bsv2-label">Notas</label>
                    <input type="text" class="bsv2-control" name="advance_notes[]" value="${safeText(values.notes, '')}" placeholder="Detalle del pago">
                </div>
            </div>
        `;

        return wrapper;
    }

    function buildBulkPaymentRow(values = {}) {
        const wrapper = document.createElement('div');
        wrapper.className = 'bsv2-pay-line';

        const accountId = safeText(values.accountId, '');
        const clientName = safeText(values.clientName, '');
        const displayClient = accountId !== '' ? accountId + (clientName ? ' · ' + clientName : '') : '';

        wrapper.innerHTML = `
            <div class="bsv2-pay-line__grid bsv2-pay-line__grid--bulk">
                <div class="bsv2-field">
                    <label class="bsv2-label">Cliente / ID</label>
                    <input type="text" class="bsv2-control" name="bulk_client[]" value="${displayClient}" placeholder="Cuenta, RFC o nombre">
                </div>

                <div class="bsv2-field">
                    <label class="bsv2-label">Período</label>
                    <input type="month" class="bsv2-control" name="bulk_period[]" value="${safeText(values.period, '')}">
                </div>

                <div class="bsv2-field">
                    <label class="bsv2-label">Monto</label>
                    <input type="number" step="0.01" min="0" class="bsv2-control" name="bulk_amount[]" value="${safeText(values.amount, '')}" placeholder="0.00">
                </div>

                <div class="bsv2-field">
                    <label class="bsv2-label">Método</label>
                    <select class="bsv2-control" name="bulk_method[]">
                        <option value="transferencia" ${safeText(values.method, 'transferencia') === 'transferencia' ? 'selected' : ''}>Transferencia</option>
                        <option value="deposito" ${safeText(values.method, '') === 'deposito' ? 'selected' : ''}>Depósito</option>
                        <option value="efectivo" ${safeText(values.method, '') === 'efectivo' ? 'selected' : ''}>Efectivo</option>
                        <option value="stripe" ${safeText(values.method, '') === 'stripe' ? 'selected' : ''}>Stripe</option>
                        <option value="manual" ${safeText(values.method, '') === 'manual' ? 'selected' : ''}>Manual</option>
                    </select>
                </div>

                <div class="bsv2-field">
                    <label class="bsv2-label">Referencia</label>
                    <input type="text" class="bsv2-control" name="bulk_reference[]" value="${safeText(values.reference, '')}" placeholder="Folio o referencia">
                </div>
            </div>
        `;

        return wrapper;
    }

    function resetAdvanceModal() {
        if (advanceClientInput) {
            advanceClientInput.value = '';
            advanceClientInput.dataset.accountId = '';
        }

        if (advancePaymentDateInput) {
            advancePaymentDateInput.value = new Date().toISOString().slice(0, 10);
        }

        if (advanceMethodInput) {
            advanceMethodInput.value = 'transferencia';
        }

        if (advanceReferenceInput) {
            advanceReferenceInput.value = '';
        }

        if (advanceRows) {
            advanceRows.innerHTML = '';
            advanceRows.appendChild(buildAdvanceRow());
        }
    }

    function resetBulkPaymentsModal() {
        if (bulkPaymentsRows) {
            bulkPaymentsRows.innerHTML = '';
            bulkPaymentsRows.appendChild(buildBulkPaymentRow());
        }
    }

    function closeModal(modal, restoreFocus = true) {
        if (!modal) {
            return;
        }

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (modal === previewModal) resetPreviewModal();
        if (modal === editModal) resetEditModal();
        if (modal === emailModal) resetEmailModal();
        if (modal === advanceModal) resetAdvanceModal();
        if (modal === bulkPaymentsModal) resetBulkPaymentsModal();

        if (activeModal === modal) {
            activeModal = null;
        }

        unlockScroll();

        if (restoreFocus) {
            const firstToggle = root.querySelector('[data-bsv2-toggle-actions]');
            if (firstToggle) {
                firstToggle.focus();
            }
        }
    }

    function closeActiveModal() {
        if (!activeModal) {
            return;
        }

        closeModal(activeModal);
    }

    function getSelectedRows() {
        return rowCheckboxes
            .filter((checkbox) => checkbox.checked)
            .map((checkbox) => ({
                statementId: safeText(checkbox.value, ''),
                accountId: safeText(checkbox.dataset.accountId, ''),
                period: safeText(checkbox.dataset.period, ''),
                clientName: safeText(checkbox.dataset.clientName, 'Cliente'),
                email: safeText(checkbox.dataset.email, '')
            }));
    }

    function syncSelectedHiddenInputs() {
        if (!selectedHiddenInputsContainer) {
            return;
        }

        const selectedValues = getSelectedRows().map((row) => row.statementId);
        selectedHiddenInputsContainer.innerHTML = '';

        selectedValues.forEach((value) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = 'selected_ids[]';
            input.value = value;
            selectedHiddenInputsContainer.appendChild(input);
        });
    }

    function updateRowState(checkbox) {
        const row = checkbox.closest('[data-statement-row]');
        if (!row) {
            return;
        }

        row.classList.toggle('is-selected', checkbox.checked);
    }

    function updateSelectedCount() {
        const total = getSelectedRows().length;

        if (selectedCountNode) {
            selectedCountNode.textContent = String(total);
        }

        if (masterCheckbox) {
            const allChecked = rowCheckboxes.length > 0 && rowCheckboxes.every((checkbox) => checkbox.checked);
            const someChecked = rowCheckboxes.some((checkbox) => checkbox.checked);

            masterCheckbox.checked = allChecked;
            masterCheckbox.indeterminate = !allChecked && someChecked;
        }

        rowCheckboxes.forEach(updateRowState);
        syncSelectedHiddenInputs();
    }

    function bindSelection() {
        rowCheckboxes.forEach((checkbox) => {
            checkbox.addEventListener('change', updateSelectedCount);
            updateRowState(checkbox);
        });

        if (masterCheckbox) {
            masterCheckbox.addEventListener('change', function () {
                rowCheckboxes.forEach((checkbox) => {
                    checkbox.checked = masterCheckbox.checked;
                });

                updateSelectedCount();
            });
        }

        if (selectAllVisibleButton) {
            selectAllVisibleButton.addEventListener('click', function () {
                rowCheckboxes.forEach((checkbox) => {
                    checkbox.checked = true;
                });

                updateSelectedCount();
            });
        }

        if (clearSelectedButton) {
            clearSelectedButton.addEventListener('click', function () {
                rowCheckboxes.forEach((checkbox) => {
                    checkbox.checked = false;
                });

                updateSelectedCount();
            });
        }

        if (filtersForm) {
            filtersForm.addEventListener('submit', function () {
                syncSelectedHiddenInputs();
            });
        }
    }

    function openPreviewFromButton(button) {
        if (!previewModal) return;

        const url = safeText(parseDataset(button, 'previewUrl', ''), '');
        const title = safeText(parseDataset(button, 'previewTitle', ''), 'Vista previa del estado de cuenta');
        const period = safeText(parseDataset(button, 'previewPeriod', ''), 'Revisión rápida antes de descargar o enviar.');

        if (previewTitle) previewTitle.textContent = title;
        if (previewSubtitle) previewSubtitle.textContent = period;
        if (previewOpenTab) previewOpenTab.setAttribute('href', url || '#');
        if (previewIframe) previewIframe.setAttribute('src', url || 'about:blank');

        if (previewPlaceholder) {
            if (url) {
                previewPlaceholder.classList.add('is-hidden');
            } else {
                previewPlaceholder.textContent = 'No se encontró la vista previa de este estado de cuenta.';
                previewPlaceholder.classList.remove('is-hidden');
            }
        }

        openModal(previewModal, previewOpenTab);
    }

    function openEditFromButton(button) {
        if (!editModal || !editForm) return;

        const actionUrl = safeText(parseDataset(button, 'editUrl', ''), '#');
        const accountId = safeText(parseDataset(button, 'accountId', ''), '');
        const period = safeText(parseDataset(button, 'period', ''), '');
        const periodLabel = safeText(parseDataset(button, 'periodLabel', ''), period);
        const clientName = safeText(parseDataset(button, 'clientName', ''), 'Cliente');
        const status = normalizeStatusValue(parseDataset(button, 'status', 'pendiente'));
        const total = safeText(parseDataset(button, 'total', '0'), '0');
        const paidAt = safeText(parseDataset(button, 'lastPayment', ''), '');
        const paymentMethod = safeText(parseDataset(button, 'paymentMethod', 'manual'), 'manual');
        const paymentReference = safeText(parseDataset(button, 'paymentReference', ''), '');
        const paymentNotes = safeText(parseDataset(button, 'paymentNotes', ''), '');

        editForm.setAttribute('action', actionUrl || '#');
        if (editAccountId) editAccountId.value = accountId;
        if (editPeriod) editPeriod.value = period;
        if (editClientName) editClientName.textContent = clientName;
        if (editPeriodLabel) editPeriodLabel.textContent = periodLabel;
        if (editTotal) editTotal.textContent = safeMoney(total);
        if (editStatus) editStatus.value = status || 'pendiente';
        if (editPaymentMethod) editPaymentMethod.value = paymentMethod || 'manual';
        if (editPaidAt) editPaidAt.value = paidAt;
        if (editPaymentReference) editPaymentReference.value = paymentReference;
        if (editPaymentNotes) editPaymentNotes.value = paymentNotes;
        if (editSubtitle) {
            editSubtitle.textContent = 'Actualiza el estatus y registra la información del pago para ' + clientName + '.';
        }

        openModal(editModal, editStatus);
    }

    function openEmailFromButton(button) {
        if (!emailModal || !emailForm) return;

        const sendUrl = safeText(parseDataset(button, 'emailSendUrl', ''), '#');
        const accountId = safeText(parseDataset(button, 'accountId', ''), '');
        const period = safeText(parseDataset(button, 'period', ''), '');
        const periodLabel = safeText(parseDataset(button, 'periodLabel', ''), period);
        const clientName = safeText(parseDataset(button, 'clientName', ''), 'Cliente');
        const to = safeText(parseDataset(button, 'emailTo', ''), '');
        const subject = safeText(parseDataset(button, 'emailSubject', ''), '');
        const message = safeText(parseDataset(button, 'emailMessage', ''), '');

        emailForm.setAttribute('action', sendUrl || '#');
        if (emailAccountId) emailAccountId.value = accountId;
        if (emailPeriod) emailPeriod.value = period;
        if (emailClientName) emailClientName.textContent = clientName;
        if (emailPeriodLabel) emailPeriodLabel.textContent = periodLabel;
        if (emailTo) emailTo.value = to;
        if (emailSubject) emailSubject.value = subject;
        if (emailMessage) emailMessage.value = message;
        if (emailSubtitle) {
            emailSubtitle.textContent = 'Configura destinatarios y contenido del correo para ' + clientName + '.';
        }

        openModal(emailModal, emailTo);
    }

    function bindModalOpeners() {
        root.addEventListener('click', function (event) {
            const previewButton = event.target.closest('[data-bsv2-open-preview]');
            if (previewButton && !previewButton.classList.contains('is-disabled')) {
                event.preventDefault();
                openPreviewFromButton(previewButton);
                return;
            }

            const editButton = event.target.closest('[data-bsv2-open-edit]');
            if (editButton && !editButton.classList.contains('is-disabled')) {
                event.preventDefault();
                openEditFromButton(editButton);
                return;
            }

            const emailButton = event.target.closest('[data-bsv2-open-email]');
            if (emailButton && !emailButton.classList.contains('is-disabled')) {
                event.preventDefault();
                openEmailFromButton(emailButton);
            }
        });

        if (openAdvanceModalButton) {
            openAdvanceModalButton.addEventListener('click', function () {
                const selectedRows = getSelectedRows();

                if (selectedRows.length === 1 && advanceClientInput) {
                    advanceClientInput.value = selectedRows[0].accountId + ' · ' + selectedRows[0].clientName;
                    advanceClientInput.dataset.accountId = selectedRows[0].accountId;
                } else if (advanceClientInput) {
                    advanceClientInput.value = '';
                    advanceClientInput.dataset.accountId = '';
                }

                openModal(advanceModal, advanceClientInput);
            });
        }

        if (openBulkPaymentsModalButton) {
            openBulkPaymentsModalButton.addEventListener('click', function () {
                const selectedRows = getSelectedRows();

                if (bulkPaymentsRows) {
                    bulkPaymentsRows.innerHTML = '';

                    if (selectedRows.length > 0) {
                        selectedRows.forEach((row) => {
                            bulkPaymentsRows.appendChild(buildBulkPaymentRow(row));
                        });
                    } else {
                        bulkPaymentsRows.appendChild(buildBulkPaymentRow());
                    }
                }

                openModal(bulkPaymentsModal, bulkPaymentsRows?.querySelector('input'));
            });
        }
    }

    function bindModalClosers() {
        root.addEventListener('click', function (event) {
            const closeTrigger = event.target.closest('[data-bsv2-close-modal]');
            if (!closeTrigger) {
                return;
            }

            event.preventDefault();
            closeActiveModal();
        });
    }

    function bindGlobalOutsideClick() {
        root.addEventListener('click', function (event) {
            const clickedInsideDropdown = event.target.closest('.bsv2-actions-dropdown');

            if (!clickedInsideDropdown) {
                closeAllActionMenus();
            }
        });
    }

    function bindKeyboard() {
        root.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                if (activeModal) {
                    event.preventDefault();
                    closeActiveModal();
                    return;
                }

                closeAllActionMenus();
            }

            if (event.key === 'Tab' && activeModal) {
                const focusables = getFocusableElements(activeModal);

                if (focusables.length === 0) {
                    return;
                }

                const first = focusables[0];
                const last = focusables[focusables.length - 1];
                const current = document.activeElement;

                if (event.shiftKey && current === first) {
                    event.preventDefault();
                    last.focus();
                    return;
                }

                if (!event.shiftKey && current === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        });
    }

    function bindResize() {
        window.addEventListener('resize', function () {
            closeAllActionMenus();
        });
    }

    function bindFormStateRules() {
        if (!editStatus || !editPaidAt) {
            return;
        }

        const refreshEditFields = function () {
            const status = normalizeStatusValue(editStatus.value);

            if (status === 'pagado') {
                editPaidAt.removeAttribute('disabled');
                editPaidAt.closest('.bsv2-field')?.classList.remove('is-disabled');
                return;
            }

            editPaidAt.value = '';
            editPaidAt.setAttribute('disabled', 'disabled');
            editPaidAt.closest('.bsv2-field')?.classList.add('is-disabled');
        };

        editStatus.addEventListener('change', refreshEditFields);
        refreshEditFields();
    }

    function bindSubmitProtection() {
        [editForm, emailForm].forEach(function (form) {
            if (!form) {
                return;
            }

            form.addEventListener('submit', function () {
                const submitButton = form.querySelector('button[type="submit"]');
                if (!submitButton) {
                    return;
                }

                submitButton.setAttribute('disabled', 'disabled');
                submitButton.classList.add('is-disabled');
            });
        });
    }

    function bindDynamicRows() {
        if (addAdvanceRowButton && advanceRows) {
            addAdvanceRowButton.addEventListener('click', function () {
                advanceRows.appendChild(buildAdvanceRow());
            });
        }

        if (addBulkPaymentRowButton && bulkPaymentsRows) {
            addBulkPaymentRowButton.addEventListener('click', function () {
                bulkPaymentsRows.appendChild(buildBulkPaymentRow());
            });
        }
    }

    async function postJson(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {})
            },
            body: JSON.stringify(payload)
        });

        let data = {};
        try {
            data = await response.json();
        } catch (error) {
            data = {};
        }

        if (!response.ok) {
            throw new Error(safeText(data.message, 'No se pudo completar la operación.'));
        }

        return data;
    }

    function getCurrentFilterPayload() {
        const formData = new FormData(filtersForm || undefined);
        const payload = Object.fromEntries(formData.entries());

        payload.selected_ids = formData.getAll('selected_ids[]');

        return payload;
    }

    async function handleBulkSend(mode) {
        const selectedRows = getSelectedRows();

        if (mode === 'selected' && selectedRows.length === 0) {
            showMessage('Primero selecciona al menos un estado de cuenta.', 'error');
            return;
        }

        const url = getRouteUrl('bulkSend');
        if (!url) {
            showMessage('No se encontró la ruta de envío masivo.', 'error');
            return;
        }

        const payload = {
            ...getCurrentFilterPayload(),
            mode,
            selected_ids: selectedRows.map((row) => row.statementId)
        };

        try {
            const data = await postJson(url, payload);
            showMessage(safeText(data.message, 'Operación completada.'));
            window.location.reload();
        } catch (error) {
            showMessage(error.message, 'error');
        }
    }

    function buildAdvancePayload() {
        const accountText = safeText(advanceClientInput?.value, '');
        const accountId = safeText(advanceClientInput?.dataset.accountId, '') || accountText.split('·')[0].trim();

        const periods = Array.from(advanceRows?.querySelectorAll('input[name="advance_period[]"]') || []);
        const types = Array.from(advanceRows?.querySelectorAll('select[name="advance_type[]"]') || []);
        const amounts = Array.from(advanceRows?.querySelectorAll('input[name="advance_amount[]"]') || []);
        const notes = Array.from(advanceRows?.querySelectorAll('input[name="advance_notes[]"]') || []);

        const lines = periods.map((periodInput, index) => ({
            period: safeText(periodInput.value, ''),
            type: safeText(types[index]?.value, 'full'),
            amount: safeText(amounts[index]?.value, '0'),
            notes: safeText(notes[index]?.value, '')
        })).filter((line) => line.period !== '' && Number.parseFloat(line.amount) > 0);

        return {
            account_id: accountId,
            payment_date: safeText(advancePaymentDateInput?.value, ''),
            payment_method: safeText(advanceMethodInput?.value, 'transferencia'),
            payment_reference: safeText(advanceReferenceInput?.value, ''),
            lines
        };
    }

    function buildBulkPaymentsPayload() {
        const clients = Array.from(bulkPaymentsRows?.querySelectorAll('input[name="bulk_client[]"]') || []);
        const periods = Array.from(bulkPaymentsRows?.querySelectorAll('input[name="bulk_period[]"]') || []);
        const amounts = Array.from(bulkPaymentsRows?.querySelectorAll('input[name="bulk_amount[]"]') || []);
        const methods = Array.from(bulkPaymentsRows?.querySelectorAll('select[name="bulk_method[]"]') || []);
        const references = Array.from(bulkPaymentsRows?.querySelectorAll('input[name="bulk_reference[]"]') || []);

        const payments = clients.map((clientInput, index) => {
            const rawClient = safeText(clientInput.value, '');
            const accountId = rawClient.split('·')[0].trim();

            return {
                account_id: accountId,
                period: safeText(periods[index]?.value, ''),
                amount: safeText(amounts[index]?.value, '0'),
                method: safeText(methods[index]?.value, 'transferencia'),
                reference: safeText(references[index]?.value, ''),
                concept: 'Pago masivo manual desde estados de cuenta V2',
                also_apply: true
            };
        }).filter((payment) => payment.account_id !== '' && payment.period !== '' && Number.parseFloat(payment.amount) > 0);

        return { payments };
    }

    function bindToolbarActions() {
        if (sendAllButton) {
            sendAllButton.addEventListener('click', function () {
                handleBulkSend('visible');
            });
        }

        if (sendSelectedButton) {
            sendSelectedButton.addEventListener('click', function () {
                handleBulkSend('selected');
            });
        }

        if (confirmAdvancePaymentButton) {
            confirmAdvancePaymentButton.addEventListener('click', async function () {
                const url = getRouteUrl('advancePayments');
                const payload = buildAdvancePayload();

                if (!url) {
                    showMessage('No se encontró la ruta para adelantar pagos.', 'error');
                    return;
                }

                if (!payload.account_id) {
                    showMessage('Debes capturar o seleccionar un cliente.', 'error');
                    return;
                }

                if (!Array.isArray(payload.lines) || payload.lines.length === 0) {
                    showMessage('Debes agregar al menos un período con monto.', 'error');
                    return;
                }

                try {
                    confirmAdvancePaymentButton.setAttribute('disabled', 'disabled');
                    confirmAdvancePaymentButton.classList.add('is-disabled');

                    const data = await postJson(url, payload);
                    showMessage(safeText(data.message, 'Adelanto registrado correctamente.'));
                    closeModal(advanceModal, false);
                    window.location.reload();
                } catch (error) {
                    showMessage(error.message, 'error');
                } finally {
                    confirmAdvancePaymentButton.removeAttribute('disabled');
                    confirmAdvancePaymentButton.classList.remove('is-disabled');
                }
            });
        }

        if (confirmBulkPaymentsButton) {
            confirmBulkPaymentsButton.addEventListener('click', async function () {
                const url = getRouteUrl('bulkPayments');
                const payload = buildBulkPaymentsPayload();

                if (!url) {
                    showMessage('No se encontró la ruta para pagos masivos.', 'error');
                    return;
                }

                if (!Array.isArray(payload.payments) || payload.payments.length === 0) {
                    showMessage('Debes capturar al menos un pago válido.', 'error');
                    return;
                }

                try {
                    confirmBulkPaymentsButton.setAttribute('disabled', 'disabled');
                    confirmBulkPaymentsButton.classList.add('is-disabled');

                    const data = await postJson(url, payload);
                    showMessage(safeText(data.message, 'Pagos registrados correctamente.'));
                    closeModal(bulkPaymentsModal, false);
                    window.location.reload();
                } catch (error) {
                    showMessage(error.message, 'error');
                } finally {
                    confirmBulkPaymentsButton.removeAttribute('disabled');
                    confirmBulkPaymentsButton.classList.remove('is-disabled');
                }
            });
        }
    }

    window.addEventListener('resize', repositionOpenDropdown);
    window.addEventListener('scroll', repositionOpenDropdown, true);

    setupAccordion('bsv2-kpis-toggle', 'bsv2-kpis-content', true);
    setupAccordion('bsv2-filters-toggle', 'bsv2-filters-content', true);
    setupAccordion('bsv2-list-toggle', 'bsv2-list-content', false);

    resetPreviewModal();
    resetEditModal();
    resetEmailModal();
    resetAdvanceModal();
    resetBulkPaymentsModal();

    bindSelection();
    bindActionButtons();
    bindMenuClicks();
    bindModalOpeners();
    bindModalClosers();
    bindGlobalOutsideClick();
    bindKeyboard();
    bindResize();
    bindFormStateRules();
    bindSubmitProtection();
    bindDynamicRows();
    bindToolbarActions();

    closeAllActionMenus();
    updateSelectedCount();
})();