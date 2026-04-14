/* C:\wamp64\www\pactopia360_erp\public\assets\client\js\sat\sat-portal-v1-extra.js */
(function () {
    'use strict';

    const APP = window.P360_SAT || {};
    APP.quoteTransferProofUrl = APP.quoteTransferProofUrl || '/cliente/sat/quote/transfer-proof';

    const qs = (selector, scope = document) => scope.querySelector(selector);
    const qsa = (selector, scope = document) => Array.from(scope.querySelectorAll(selector));

    const normalizeText = (value) => {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase()
            .trim();
    };

    const formatMoney = (value) => {
        const amount = Number(value || 0);
        if (!Number.isFinite(amount)) {
            return 'Pendiente';
        }

        return `$${amount.toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        })}`;
    };

    const statusLabelMap = {
        borrador: 'Borrador',
        simulada: 'Simulada',
        en_proceso: 'En proceso',
        cotizada: 'Cotizada',
        pendiente_pago: 'Pendiente de pago',
        pagada: 'Pagada',
        en_descarga: 'En proceso de descarga',
        completada: 'Completada',
        cancelada: 'Cancelada',
    };

    const statusBadgeClass = (status) => {
        if (status === 'completada') {
            return 'is-success';
        }

        if (['pagada', 'cotizada', 'en_proceso', 'pendiente_pago', 'en_descarga'].includes(status)) {
            return 'is-warning';
        }

        return 'is-muted';
    };

    const parseDateForInput = (value) => {
        const raw = String(value || '').trim();
        if (!raw) {
            return '';
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            return raw;
        }

        if (/^\d{4}-\d{2}$/.test(raw)) {
            return `${raw}-01`;
        }

        if (/^\d{2}\/\d{2}\/\d{4}/.test(raw)) {
            const [datePart] = raw.split(' ');
            const [day, month, year] = datePart.split('/');
            if (year && month && day) {
                return `${year}-${month}-${day}`;
            }
        }

        const parsed = new Date(raw);
        if (Number.isNaN(parsed.getTime())) {
            return '';
        }

        const year = parsed.getFullYear();
        const month = String(parsed.getMonth() + 1).padStart(2, '0');
        const day = String(parsed.getDate()).padStart(2, '0');

        return `${year}-${month}-${day}`;
    };

    const formatDateForDetail = (value) => {
        const raw = String(value || '').trim();
        if (!raw) {
            return 'Sin fecha';
        }

        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            const [year, month, day] = raw.split('-');
            return `${day}/${month}/${year}`;
        }

        if (/^\d{4}-\d{2}$/.test(raw)) {
            const [year, month] = raw.split('-');
            return `${month}/${year}`;
        }

        return raw;
    };

    const setText = (selector, value, scope = document) => {
        const node = qs(selector, scope);
        if (node) {
            node.textContent = value;
        }
    };

    const setInputValue = (selector, value, scope = document) => {
        const node = qs(selector, scope);
        if (node) {
            node.value = value;
        }
    };

    const openModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.setAttribute('aria-hidden', 'false');
        modal.classList.add('is-open');
        document.documentElement.classList.add('sat-clean-modal-open');
        document.body.classList.add('sat-clean-modal-open');
    };

    const closeModal = (modal) => {
        if (!modal) {
            return;
        }

        modal.setAttribute('aria-hidden', 'true');
        modal.classList.remove('is-open');

        if (!qs('.sat-clean-modal.is-open')) {
            document.documentElement.classList.remove('sat-clean-modal-open');
            document.body.classList.remove('sat-clean-modal-open');
        }
    };

    const closeAllModals = () => {
        qsa('.sat-clean-modal.is-open').forEach(closeModal);
    };

    const getCsrfToken = () => {
        const tokenFromMeta = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';
        if (tokenFromMeta) {
            return tokenFromMeta;
        }

        const tokenFromInput = document.querySelector('input[name="_token"]')?.value || '';
        return tokenFromInput;
    };

    const buildPostFormAndSubmit = (url, data = {}) => {
        if (!url) {
            window.alert('No se encontró la ruta para procesar el pago.');
            return;
        }

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;
        form.style.display = 'none';

        const csrf = getCsrfToken();
        if (csrf) {
            const tokenInput = document.createElement('input');
            tokenInput.type = 'hidden';
            tokenInput.name = '_token';
            tokenInput.value = csrf;
            form.appendChild(tokenInput);
        }

        Object.entries(data).forEach(([key, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = String(value ?? '');
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    };

        const submitMultipart = async (url, formData) => {
        const csrf = getCsrfToken();

        const response = await fetch(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: formData,
            credentials: 'same-origin',
        });

        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            throw new Error(data.msg || data.message || 'No se pudo procesar la solicitud.');
        }

        return data;
    };

    const getMainQuoteModal = () => {
        const exactSelectors = [
            '#satQuoteMainModal',
            '#satQuoteCreateModal',
            '#satNewQuoteModal',
            '#quoteMainModal',
            '#quoteCreateModal',
            '#newQuoteModal'
        ];

        for (const selector of exactSelectors) {
            const modal = qs(selector);
            if (modal) {
                return modal;
            }
        }

        const allModals = qsa('.sat-clean-modal');
        return allModals.find((modal) => {
            const title = normalizeText(
                qs('.sat-clean-modal__title', modal)?.textContent ||
                qs('h2', modal)?.textContent ||
                ''
            );

            return title.includes('nueva cotizacion');
        }) || null;
    };

    const setFirstExistingValue = (selectors, value, scope = document) => {
        if (value === undefined || value === null) {
            return;
        }

        for (const selector of selectors) {
            const node = qs(selector, scope);
            if (!node) {
                continue;
            }

            if ('value' in node) {
                node.value = value;
                node.dispatchEvent(new Event('input', { bubbles: true }));
                node.dispatchEvent(new Event('change', { bubbles: true }));
                return;
            }
        }
    };

    const preloadQuoteIntoMainModal = (data = {}) => {
        const modal = getMainQuoteModal();
        if (!modal) {
            return false;
        }

        setFirstExistingValue([
            '#satQuoteEditDraftId',
            '#satQuoteDraftId',
            'input[name="draft_id"]',
            'input[name="quote_id"]'
        ], data.id || '', modal);

        setFirstExistingValue([
            '#satQuoteRfc',
            '#satQuoteSelectedRfc',
            'input[name="rfc"]',
            'input[name="rfc_owner"]'
        ], data.rfc || '', modal);

        setFirstExistingValue([
            '#satQuoteDateFrom',
            'input[name="date_from"]'
        ], parseDateForInput(data.dateFrom || ''), modal);

        setFirstExistingValue([
            '#satQuoteDateTo',
            'input[name="date_to"]'
        ], parseDateForInput(data.dateTo || ''), modal);

        setFirstExistingValue([
            '#satQuoteType',
            'select[name="tipo"]',
            'select[name="tipo_solicitud"]',
            'select[name="quote_type"]'
        ], data.tipo || 'emitidos', modal);

        setFirstExistingValue([
            '#satQuoteConcepto',
            'textarea[name="concepto"]',
            'textarea[name="notes"]',
            'textarea[name="quote_notes"]'
        ], data.concepto || '', modal);

        return true;
    };

    const openMainQuoteModal = (data = {}) => {
        const modal = getMainQuoteModal();
        if (!modal) {
            window.alert('No se encontró el modal principal de cotización.');
            return;
        }

        preloadQuoteIntoMainModal(data);
        closeAllModals();
        openModal(modal);
    };

        const ensurePaymentChoiceModal = () => {
        let modal = qs('#satQuotePaymentChoiceModal');
        if (modal) {
            return modal;
        }

        const wrapper = document.createElement('div');
        wrapper.innerHTML = `
            <div class="sat-clean-modal" id="satQuotePaymentChoiceModal" aria-hidden="true">
                <div class="sat-clean-modal__backdrop" data-quote-payment-close></div>
                <div class="sat-clean-modal__dialog sat-clean-modal__dialog--xl" role="dialog" aria-modal="true" aria-labelledby="satQuotePaymentChoiceTitle">
                    <div class="sat-clean-modal__header">
                        <div>
                            <h2 class="sat-clean-modal__title" id="satQuotePaymentChoiceTitle">Selecciona método de pago</h2>
                            <p class="sat-clean-modal__subtitle">Puedes pagar con Stripe o registrar pago por transferencia con comprobante.</p>
                        </div>

                        <button type="button" class="sat-clean-modal__close" data-quote-payment-close aria-label="Cerrar">
                            ✕
                        </button>
                    </div>

                    <div class="sat-clean-modal__body-scroll">
                        <div class="sat-clean-form-section">
                            <div class="sat-clean-form-section__head">
                                <h3 class="sat-clean-form-section__title">Cotización seleccionada</h3>
                            </div>

                            <div class="sat-clean-form-grid sat-clean-form-grid--2">
                                <div class="sat-clean-form-field">
                                    <label>Folio</label>
                                    <input type="text" id="satQuotePaymentChoiceFolio" readonly>
                                </div>

                                <div class="sat-clean-form-field">
                                    <label>RFC</label>
                                    <input type="text" id="satQuotePaymentChoiceRfc" readonly>
                                </div>

                                <div class="sat-clean-form-field">
                                    <label>Total</label>
                                    <input type="text" id="satQuotePaymentChoiceTotal" readonly>
                                </div>

                                <div class="sat-clean-form-field">
                                    <label>Estatus</label>
                                    <input type="text" id="satQuotePaymentChoiceStatus" readonly>
                                </div>
                            </div>
                        </div>

                        <div class="sat-clean-form-section">
                            <div class="sat-clean-modal__actions" style="justify-content:flex-start; gap:12px; flex-wrap:wrap;">
                                <button type="button" class="sat-clean-btn sat-clean-btn--primary" id="satQuotePayStripeBtn">
                                    Pagar con Stripe
                                </button>

                                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" id="satQuotePayTransferToggleBtn">
                                    Pagar por transferencia
                                </button>

                                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-quote-payment-close>
                                    Cancelar
                                </button>
                            </div>
                        </div>

                        <form id="satQuoteTransferForm" class="sat-clean-modal__form" style="display:none;" enctype="multipart/form-data" autocomplete="off">
                            <input type="hidden" name="sat_download_id" id="satQuoteTransferId">

                            <section class="sat-clean-form-section">
                                <div class="sat-clean-form-section__head">
                                    <h3 class="sat-clean-form-section__title">Pago por transferencia</h3>
                                    <p class="sat-clean-form-section__text">Sube comprobante PDF o imagen. Este pago quedará en revisión.</p>
                                </div>

                                <div class="sat-clean-form-grid sat-clean-form-grid--3">
                                    <div class="sat-clean-form-field">
                                        <label for="sat_transfer_bank_name">Banco destino</label>
                                        <input type="text" id="sat_transfer_bank_name" name="bank_name" maxlength="120" required>
                                    </div>

                                    <div class="sat-clean-form-field">
                                        <label for="sat_transfer_account_holder">Beneficiario</label>
                                        <input type="text" id="sat_transfer_account_holder" name="account_holder" maxlength="190">
                                    </div>

                                    <div class="sat-clean-form-field">
                                        <label for="sat_transfer_reference">Referencia</label>
                                        <input type="text" id="sat_transfer_reference" name="reference" maxlength="120" required>
                                    </div>

                                    <div class="sat-clean-form-field">
                                        <label for="sat_transfer_date">Fecha transferencia</label>
                                        <input type="date" id="sat_transfer_date" name="transfer_date" required>
                                    </div>

                                    <div class="sat-clean-form-field">
                                        <label for="sat_transfer_amount">Monto transferido</label>
                                        <input type="number" id="sat_transfer_amount" name="transfer_amount" min="0.01" step="0.01" required>
                                    </div>

                                    <div class="sat-clean-form-field">
                                        <label for="sat_transfer_payer_name">Ordenante</label>
                                        <input type="text" id="sat_transfer_payer_name" name="payer_name" maxlength="190">
                                    </div>

                                    <div class="sat-clean-form-field">
                                        <label for="sat_transfer_payer_bank">Banco ordenante</label>
                                        <input type="text" id="sat_transfer_payer_bank" name="payer_bank" maxlength="120">
                                    </div>

                                    <div class="sat-clean-form-field sat-clean-form-field--span-2">
                                        <label for="sat_transfer_proof_file">Comprobante</label>
                                        <input type="file" id="sat_transfer_proof_file" name="proof_file" accept=".pdf,.jpg,.jpeg,.png,.webp" required>
                                    </div>

                                    <div class="sat-clean-form-field sat-clean-form-field--full">
                                        <label for="sat_transfer_notes">Notas</label>
                                        <textarea id="sat_transfer_notes" name="notes" rows="3" placeholder="Comentarios adicionales del pago"></textarea>
                                    </div>
                                </div>
                            </section>

                            <div class="sat-clean-modal__actions">
                                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" id="satQuoteTransferBackBtn">
                                    Volver
                                </button>

                                <button type="submit" class="sat-clean-btn sat-clean-btn--primary" id="satQuoteTransferSubmitBtn">
                                    Enviar comprobante
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        `.trim();

        document.body.appendChild(wrapper.firstElementChild);
        modal = qs('#satQuotePaymentChoiceModal');

        qsa('[data-quote-payment-close]', modal).forEach((button) => {
            button.addEventListener('click', () => closeModal(modal));
        });

        const toggleBtn = qs('#satQuotePayTransferToggleBtn', modal);
        const transferForm = qs('#satQuoteTransferForm', modal);
        const backBtn = qs('#satQuoteTransferBackBtn', modal);

        if (toggleBtn && transferForm) {
            toggleBtn.addEventListener('click', () => {
                transferForm.style.display = 'block';
                toggleBtn.style.display = 'none';
            });
        }

        if (backBtn && transferForm && toggleBtn) {
            backBtn.addEventListener('click', () => {
                transferForm.style.display = 'none';
                toggleBtn.style.display = 'inline-flex';
            });
        }

        return modal;
    };

        const openPaymentChoiceModal = (row) => {
        const modal = ensurePaymentChoiceModal();
        if (!modal || !row) {
            return;
        }

        const data = extractQuoteData(row);

        setInputValue('#satQuotePaymentChoiceFolio', data.folio || '', modal);
        setInputValue('#satQuotePaymentChoiceRfc', data.rfc || '', modal);
        setInputValue('#satQuotePaymentChoiceTotal', data.totalLabel || 'Pendiente', modal);
        setInputValue('#satQuotePaymentChoiceStatus', data.statusLabel || 'Pendiente', modal);
        setInputValue('#satQuoteTransferId', data.id || '', modal);
        setInputValue('#sat_transfer_amount', data.total || '', modal);

        const transferForm = qs('#satQuoteTransferForm', modal);
        const transferToggleBtn = qs('#satQuotePayTransferToggleBtn', modal);

        if (transferForm) {
            transferForm.reset();
            setInputValue('#satQuoteTransferId', data.id || '', modal);
            setInputValue('#sat_transfer_amount', data.total || '', modal);
            transferForm.style.display = 'none';
        }

        if (transferToggleBtn) {
            transferToggleBtn.style.display = 'inline-flex';
        }

        const stripeBtn = qs('#satQuotePayStripeBtn', modal);
        if (stripeBtn) {
            stripeBtn.onclick = () => {
                const payUrl = APP.quotePayUrl || '/cliente/sat/quote/pay';
                buildPostFormAndSubmit(payUrl, {
                    sat_download_id: data.id || ''
                });
            };
        }

        if (transferForm) {
            transferForm.onsubmit = async (event) => {
                event.preventDefault();

                const submitBtn = qs('#satQuoteTransferSubmitBtn', modal);
                const originalText = submitBtn ? submitBtn.textContent : '';

                try {
                    if (submitBtn) {
                        submitBtn.disabled = true;
                        submitBtn.textContent = 'Enviando...';
                    }

                    const formData = new FormData(transferForm);
                    formData.set('sat_download_id', data.id || '');

                    const result = await submitMultipart(
                        APP.quoteTransferProofUrl || '/cliente/sat/quote/transfer-proof',
                        formData
                    );

                    window.alert(result.msg || 'Comprobante enviado correctamente.');
                    window.location.reload();
                } catch (error) {
                    window.alert(error.message || 'No se pudo enviar el comprobante.');
                } finally {
                    if (submitBtn) {
                        submitBtn.disabled = false;
                        submitBtn.textContent = originalText || 'Enviar comprobante';
                    }
                }
            };
        }

        openModal(modal);
    };

    const bindGlobalModalClosing = () => {
        qsa('[data-rfc-close-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                const modal = button.closest('.sat-clean-modal');
                closeModal(modal);
            });
        });

        qsa('[data-quote-detail-close]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(qs('#satQuoteDetailModal'));
            });
        });

        qsa('[data-quote-edit-close]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(qs('#satQuoteEditModal'));
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key === 'Escape') {
                closeAllModals();
            }
        });
    };

    const bindLinkedInviteModals = () => {
        const createModal = qs('#satRfcCreateModal');
        const inviteSingleModal = qs('#satInviteSingleModal');
        const inviteZipModal = qs('#satInviteZipModal');

        qsa('[data-open-linked-modal="invite-single"]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(createModal);
                openModal(inviteSingleModal);
            });
        });

        qsa('[data-open-linked-modal="invite-zip"]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(createModal);
                openModal(inviteZipModal);
            });
        });

        qsa('[data-back-to-create-modal]').forEach((button) => {
            button.addEventListener('click', () => {
                closeModal(button.closest('.sat-clean-modal'));
                openModal(createModal);
            });
        });
    };

    const bindRfcModals = () => {
        const createModal = qs('#satRfcCreateModal');
        const editModal = qs('#satRfcEditModal');
        const editForm = qs('#satRfcEditForm');

        qsa('[data-rfc-open-modal="create"]').forEach((button) => {
            button.addEventListener('click', () => {
                openModal(createModal);
            });
        });

        qsa('[data-rfc-open-modal="edit"]').forEach((button) => {
            button.addEventListener('click', () => {
                if (!editModal || !editForm) {
                    return;
                }

                const id = button.dataset.rfcId || '';
                const actionTemplate = editForm.dataset.actionTemplate || '';
                const action = actionTemplate.replace('__ID__', id);

                editForm.setAttribute('action', action);

                setInputValue('#sat_edit_id', id, editModal);
                setInputValue('#sat_edit_rfc', button.dataset.rfcValue || '', editModal);
                setInputValue('#sat_edit_razon_social', button.dataset.rfcRazonSocial || '', editModal);
                setInputValue('#sat_edit_tipo_origen', button.dataset.rfcTipoOrigen || 'interno', editModal);
                setInputValue('#sat_edit_contact_name', button.dataset.rfcContactName || '', editModal);
                setInputValue('#sat_edit_contact_email', button.dataset.rfcContactEmail || '', editModal);
                setInputValue('#sat_edit_contact_phone', button.dataset.rfcContactPhone || '', editModal);
                setInputValue('#sat_edit_source_label', button.dataset.rfcSourceLabel || '', editModal);
                setInputValue('#sat_edit_notes', button.dataset.rfcNotes || '', editModal);

                openModal(editModal);
            });
        });

        qsa('[data-rfc-delete-form="true"]').forEach((form) => {
            form.addEventListener('submit', (event) => {
                const rfc = qs('input[name="rfc"]', form)?.value || 'este RFC';
                const ok = window.confirm(`¿Deseas eliminar ${rfc}?`);
                if (!ok) {
                    event.preventDefault();
                }
            });
        });
    };

    const bindPasswordReveal = () => {
        qsa('[data-password-reveal-url]').forEach((button) => {
            button.addEventListener('click', async () => {
                const url = button.dataset.passwordRevealUrl;
                const label = button.dataset.passwordLabel || 'Contraseña';
                const rfc = button.dataset.rfc || '';

                if (!url) {
                    return;
                }

                const original = button.textContent;
                button.disabled = true;
                button.textContent = '...';

                try {
                    const response = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                    });

                    const data = await response.json().catch(() => ({}));

                    if (!response.ok) {
                        const message = data.message || `No se pudo revelar la contraseña ${label}.`;
                        throw new Error(message);
                    }

                    const password = data.password || data.value || '';
                    if (!password) {
                        throw new Error(`No hay contraseña ${label} disponible.`);
                    }

                    window.alert(`${label}${rfc ? ` · ${rfc}` : ''}\n\n${password}`);
                } catch (error) {
                    window.alert(error.message || 'Ocurrió un error al consultar la contraseña.');
                } finally {
                    button.disabled = false;
                    button.textContent = original;
                }
            });
        });
    };

    const bindRfcFilters = () => {
        const rows = qsa('[data-rfc-row="true"]');
        const filterButtons = qsa('[data-filter]');
        if (!rows.length || !filterButtons.length) {
            return;
        }

        const applyFilter = (filter) => {
            rows.forEach((row) => {
                let visible = true;

                if (filter === 'activos') {
                    visible = row.dataset.filterActive === '1';
                } else if (filter === 'con-fiel') {
                    visible = row.dataset.filterFiel === '1';
                } else if (filter === 'con-csd') {
                    visible = row.dataset.filterCsd === '1';
                }

                row.hidden = !visible;
            });

            filterButtons.forEach((button) => {
                button.classList.toggle('is-active', button.dataset.filter === filter);
            });
        };

        filterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                applyFilter(button.dataset.filter || 'todos');
            });
        });

        applyFilter('todos');
    };

    const getQuoteRows = () => qsa('[data-quote-row="true"]');

    const applyQuoteFilters = () => {
        const rows = getQuoteRows();
        const searchInput = qs('#satQuoteSearchInput');
        const activeFilterButton = qs('[data-quote-filter].is-active');
        const visibleCountNode = qs('#satQuoteVisibleCount');
        const emptyStateRow = qs('#satQuotesEmptyRow');

        const activeFilter = activeFilterButton?.dataset.quoteFilter || 'todos';
        const term = normalizeText(searchInput?.value || '');

        let visibleCount = 0;

        rows.forEach((row) => {
            const rowStatus = normalizeText(row.dataset.status || '');
            const rowSearch = normalizeText(row.dataset.search || '');

            const matchesFilter = activeFilter === 'todos' ? true : rowStatus === normalizeText(activeFilter);
            const matchesSearch = term === '' ? true : rowSearch.includes(term);

            const visible = matchesFilter && matchesSearch;
            row.hidden = !visible;

            if (visible) {
                visibleCount += 1;
            }
        });

        if (visibleCountNode) {
            visibleCountNode.textContent = String(visibleCount);
        }

        if (emptyStateRow && rows.length > 0) {
            emptyStateRow.hidden = visibleCount > 0;
        }
    };

    const bindQuoteFilters = () => {
        const buttons = qsa('[data-quote-filter]');
        const searchInput = qs('#satQuoteSearchInput');

        buttons.forEach((button) => {
            button.addEventListener('click', () => {
                buttons.forEach((item) => item.classList.remove('is-active'));
                button.classList.add('is-active');
                applyQuoteFilters();
            });
        });

        if (searchInput) {
            searchInput.addEventListener('input', applyQuoteFilters);
        }

        applyQuoteFilters();
    };

    const extractQuoteData = (row) => {
        const status = row.dataset.status || 'borrador';
        const total = row.dataset.total || '';
        const progress = Number(row.dataset.progress || 0);

        return {
            id: row.dataset.quoteId || '',
            folio: row.dataset.folio || '',
            rfc: row.dataset.rfc || '',
            razonSocial: row.dataset.razonSocial || '',
            concepto: row.dataset.concepto || '',
            status,
            statusLabel: statusLabelMap[status] || status.replace(/_/g, ' '),
            total,
            totalLabel: total ? formatMoney(total) : 'Pendiente',
            progress,
            progressLabel: `${progress}%`,
            dateFrom: row.dataset.dateFrom || '',
            dateTo: row.dataset.dateTo || '',
            tipo: row.dataset.tipo || '',
            updatedAt: qs('[data-quote-field="updated_at"]', row)?.textContent?.trim() || 'Sin fecha',
        };
    };

    const openQuoteDetailModal = (row) => {
        const modal = qs('#satQuoteDetailModal');
        if (!modal) {
            return;
        }

        const data = extractQuoteData(row);

        setInputValue('#satQuoteDetailFolio', data.folio, modal);
        setInputValue('#satQuoteDetailRfc', data.rfc, modal);
        setInputValue('#satQuoteDetailStatus', data.statusLabel, modal);
        setInputValue('#satQuoteDetailRazonSocial', data.razonSocial, modal);
        setInputValue('#satQuoteDetailTipo', data.tipo || 'Sin tipo', modal);
        setInputValue('#satQuoteDetailDateFrom', formatDateForDetail(data.dateFrom), modal);
        setInputValue('#satQuoteDetailDateTo', formatDateForDetail(data.dateTo), modal);
        setInputValue('#satQuoteDetailProgress', data.progressLabel, modal);
        setInputValue('#satQuoteDetailTotal', data.totalLabel, modal);
        setInputValue('#satQuoteDetailConcepto', data.concepto || 'Sin concepto', modal);

        const editButton = qs('#satQuoteDetailEditBtn', modal);
        if (editButton) {
            editButton.dataset.quoteId = data.id;
        }

        openModal(modal);
    };

    const openQuoteEditModal = (row) => {
        const modal = qs('#satQuoteEditModal');
        if (!modal) {
            return;
        }

        const data = extractQuoteData(row);

        setInputValue('#satQuoteEditId', data.id, modal);
        setInputValue('#satQuoteEditDraftId', data.id, modal);
        setInputValue('#satQuoteEditFolio', data.folio, modal);
        setInputValue('#satQuoteEditRfc', data.rfc, modal);
        setInputValue('#satQuoteEditTipo', data.tipo || 'emitidos', modal);
        setInputValue('#satQuoteEditDateFrom', parseDateForInput(data.dateFrom), modal);
        setInputValue('#satQuoteEditDateTo', parseDateForInput(data.dateTo), modal);
        setInputValue('#satQuoteEditTotal', data.totalLabel, modal);
        setInputValue('#satQuoteEditProgress', data.progressLabel, modal);
        setInputValue('#satQuoteEditStatus', data.statusLabel, modal);
        setInputValue('#satQuoteEditConcepto', data.concepto || '', modal);

        openModal(modal);
    };

        const bindQuoteActions = () => {
        qsa('[data-quote-action="view"]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('[data-quote-row="true"]');
                if (row) {
                    openQuoteDetailModal(row);
                }
            });
        });

        qsa('[data-quote-action="edit"]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('[data-quote-row="true"]');
                if (row) {
                    openQuoteEditModal(row);
                }
            });
        });

        qsa('[data-quote-action="pay"]').forEach((button) => {
            button.addEventListener('click', () => {
                const row = button.closest('[data-quote-row="true"]');
                if (!row) {
                    window.alert('No se encontró la cotización seleccionada.');
                    return;
                }

                openPaymentChoiceModal(row);
            });
        });

        const detailEditButton = qs('#satQuoteDetailEditBtn');
        if (detailEditButton) {
            detailEditButton.addEventListener('click', () => {
                const id = detailEditButton.dataset.quoteId || '';
                if (!id) {
                    return;
                }

                const row = qs(`[data-quote-row="true"][data-quote-id="${CSS.escape(id)}"]`);
                closeModal(qs('#satQuoteDetailModal'));

                if (row) {
                    openQuoteEditModal(row);
                }
            });
        }

        const loadMainModalBtn = qs('#satQuoteEditLoadMainModalBtn');
        if (loadMainModalBtn) {
            loadMainModalBtn.addEventListener('click', () => {
                const modal = qs('#satQuoteEditModal');
                const data = {
                    id: qs('#satQuoteEditId', modal)?.value || '',
                    rfc: qs('#satQuoteEditRfc', modal)?.value || '',
                    tipo: qs('#satQuoteEditTipo', modal)?.value || 'emitidos',
                    dateFrom: qs('#satQuoteEditDateFrom', modal)?.value || '',
                    dateTo: qs('#satQuoteEditDateTo', modal)?.value || '',
                    concepto: qs('#satQuoteEditConcepto', modal)?.value || ''
                };

                closeModal(modal);
                openMainQuoteModal(data);
            });
        }

        const newQuoteButtons = [qs('#satNewQuoteButton'), qs('#satEmptyNewQuoteButton')].filter(Boolean);
        newQuoteButtons.forEach((button) => {
            button.addEventListener('click', () => {
                openMainQuoteModal();
            });
        });
    };

    const bindCenterSatActions = () => {
        qsa('.sat-clean-btn--icon-only').forEach((button) => {
            button.addEventListener('mouseenter', () => {
                button.classList.add('is-hover');
            });

            button.addEventListener('mouseleave', () => {
                button.classList.remove('is-hover');
            });
        });
    };

    const applyInitialAccordionState = () => {
        const accordions = qsa('.sat-clean-accordion__item');
        if (!accordions.length) {
            return;
        }

        accordions.forEach((item) => {
            item.removeAttribute('open');
        });
    };

    const boot = () => {
        applyInitialAccordionState();
        bindGlobalModalClosing();
        bindLinkedInviteModals();
        bindRfcModals();
        bindPasswordReveal();
        bindRfcFilters();
        bindQuoteFilters();
        bindQuoteActions();
        bindCenterSatActions();

        if (APP && typeof APP === 'object') {
            window.P360_SAT = APP;
        }
    };

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot, { once: true });
    } else {
        boot();
    }
})();