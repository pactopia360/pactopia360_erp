/* =========================================================
   SAT Portal · Descargas Cliente
   File: public/assets/client/js/sat/sat-portal-v1.js
========================================================= */

document.addEventListener('DOMContentLoaded', () => {
    initAccordionState();
    initRfcAdminActions();
    initModalSystem();
    initToolbarFilters();
    initQuoteFilters();
    initQuoteModule();
    initQuoteSecondaryModals();
    ensurePasswordDialog();
    ensureActionTooltips();
    initKeyboardShortcuts();

    function initAccordionState() {
        const accordionItems = document.querySelectorAll('.sat-clean-accordion__item');

        accordionItems.forEach((item) => {
            item.addEventListener('toggle', () => {
                const isOpen = item.hasAttribute('open');
                const title = item.querySelector('.sat-clean-accordion__bar-title')?.textContent?.trim() || 'Sección';

                console.log(`[SAT Portal] ${title}: ${isOpen ? 'abierto' : 'cerrado'}`);
            });
        });
    }

    function initRfcAdminActions() {
        const addButtons = document.querySelectorAll('[data-rfc-open-modal="create"]');
        const editButtons = document.querySelectorAll('[data-rfc-open-modal="edit"]');
        const passwordButtons = document.querySelectorAll('[data-password-reveal-url]');
        const deleteForms = document.querySelectorAll('form[action*="rfcs/delete"], form[action*="rfcs.delete"]');

        addButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.getElementById('satRfcCreateModal');

                if (!modal) {
                    showPortalNotice('No se encontró el modal para agregar RFC.', 'warning');
                    return;
                }

                openModal(modal);
            });
        });

        editButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const id = String(button.getAttribute('data-rfc-id') || '').trim();
                const rfc = String(button.getAttribute('data-rfc-value') || '').trim();
                const razonSocial = decodeHtmlEntities(String(button.getAttribute('data-rfc-razon-social') || '').trim());

                const modal = document.getElementById('satRfcEditModal');
                const form = document.getElementById('satRfcEditForm');
                const inputId = document.getElementById('sat_edit_id');
                const inputRfc = document.getElementById('sat_edit_rfc');
                const inputRazonSocial = document.getElementById('sat_edit_razon_social');
                const inputTipoOrigen = document.getElementById('sat_edit_tipo_origen');
                const inputContactoEmail = document.getElementById('sat_edit_contact_email');
                const inputContactoPhone = document.getElementById('sat_edit_contact_phone');
                const inputContactoName = document.getElementById('sat_edit_contact_name');
                const inputSourceLabel = document.getElementById('sat_edit_source_label');
                const inputNotes = document.getElementById('sat_edit_notes');

                if (!modal || !form || !inputId || !inputRfc || !inputRazonSocial) {
                    showPortalNotice('No se pudo preparar el modal de edición.', 'warning');
                    return;
                }

                const actionTemplate = String(form.getAttribute('data-action-template') || '').trim();
                if (actionTemplate === '' || id === '') {
                    showPortalNotice('No se pudo construir la ruta de edición del RFC.', 'warning');
                    return;
                }

                form.setAttribute('action', actionTemplate.replace('__ID__', id));
                inputId.value = id;
                inputRfc.value = rfc;
                inputRazonSocial.value = razonSocial;

                if (inputTipoOrigen) {
                    inputTipoOrigen.value = String(button.getAttribute('data-rfc-tipo-origen') || 'interno').trim();
                }

                if (inputContactoEmail) {
                    inputContactoEmail.value = decodeHtmlEntities(String(button.getAttribute('data-rfc-contact-email') || '').trim());
                }

                if (inputContactoPhone) {
                    inputContactoPhone.value = decodeHtmlEntities(String(button.getAttribute('data-rfc-contact-phone') || '').trim());
                }

                if (inputContactoName) {
                    inputContactoName.value = decodeHtmlEntities(String(button.getAttribute('data-rfc-contact-name') || '').trim());
                }

                if (inputSourceLabel) {
                    inputSourceLabel.value = decodeHtmlEntities(String(button.getAttribute('data-rfc-source-label') || '').trim());
                }

                if (inputNotes) {
                    inputNotes.value = decodeHtmlEntities(String(button.getAttribute('data-rfc-notes') || '').trim());
                }

                openModal(modal);

                window.requestAnimationFrame(() => {
                    inputRazonSocial.focus();
                    inputRazonSocial.select();
                });
            });
        });

        deleteForms.forEach((form) => {
            form.addEventListener('submit', (event) => {
                const row = form.closest('tr');
                const rfc = extractRfcFromRow(row);

                const confirmed = window.confirm(`¿Deseas eliminar el RFC ${rfc}?`);
                if (!confirmed) {
                    event.preventDefault();
                    return;
                }

                console.log('[SAT Portal] Acción: Eliminar RFC', rfc);
            });
        });

        passwordButtons.forEach((button) => {
            button.addEventListener('click', async () => {
                const url = String(button.getAttribute('data-password-reveal-url') || '').trim();
                const label = String(button.getAttribute('data-password-label') || 'Contraseña').trim();
                const rfc = String(button.getAttribute('data-rfc') || 'RFC').trim();

                if (url === '') {
                    showPortalNotice('No se encontró la ruta para consultar la contraseña.', 'warning');
                    return;
                }

                const originalHtml = button.innerHTML;
                button.disabled = true;
                button.classList.add('is-loading');
                button.innerHTML = '...';

                try {
                    const response = await fetch(url, {
                        method: 'GET',
                        headers: {
                            'X-Requested-With': 'XMLHttpRequest',
                            'Accept': 'application/json',
                        },
                        credentials: 'same-origin',
                    });

                    const data = await response.json().catch(() => null);

                    if (!response.ok || !data || data.ok !== true) {
                        const msg = data && data.msg ? data.msg : `No se pudo obtener la contraseña de ${label}.`;
                        showPortalNotice(msg, 'warning');
                        return;
                    }

                    const password = typeof data.password === 'string' ? data.password.trim() : '';

                    if (password === '') {
                        showPortalNotice(`No hay contraseña guardada para ${label} en ${rfc}.`, 'info');
                        return;
                    }

                    await showPasswordDialog({
                        label,
                        rfc,
                        password,
                    });
                } catch (error) {
                    console.error('[SAT Portal] Error consultando contraseña:', error);
                    showPortalNotice(`Ocurrió un error al consultar la contraseña de ${label}.`, 'warning');
                } finally {
                    button.disabled = false;
                    button.classList.remove('is-loading');
                    button.innerHTML = originalHtml;
                }
            });
        });
    }

    function initToolbarFilters() {
        const rfcSection = document.querySelector('.sat-clean-accordion[aria-label="Administración de RFC"]');
        if (!rfcSection) {
            return;
        }

        const chips = rfcSection.querySelectorAll('.sat-clean-filter-chip[data-filter]');
        const rows = rfcSection.querySelectorAll('tbody tr[data-rfc-row="true"]');

        if (!chips.length || !rows.length) {
            return;
        }

        chips.forEach((chip) => {
            chip.addEventListener('click', () => {
                chips.forEach((item) => item.classList.remove('is-active'));
                chip.classList.add('is-active');

                const filter = String(chip.getAttribute('data-filter') || 'todos').trim();

                rows.forEach((row) => {
                    const isActive = String(row.getAttribute('data-filter-active') || '0') === '1';
                    const hasFiel = String(row.getAttribute('data-filter-fiel') || '0') === '1';
                    const hasCsd = String(row.getAttribute('data-filter-csd') || '0') === '1';

                    let visible = true;

                    switch (filter) {
                        case 'activos':
                            visible = isActive;
                            break;
                        case 'con-fiel':
                            visible = hasFiel;
                            break;
                        case 'con-csd':
                            visible = hasCsd;
                            break;
                        case 'todos':
                        default:
                            visible = true;
                            break;
                    }

                    row.style.display = visible ? '' : 'none';
                });

                updateVisibleCounter(rows);
            });
        });

        updateVisibleCounter(rows);
    }

      function initQuoteFilters() {
        const quoteSearchInput = document.getElementById('satQuoteSearchInput');
        const quoteFilterButtons = document.querySelectorAll('[data-quote-filter]');
        const quoteVisibleCount = document.getElementById('satQuoteVisibleCount');

        window.P360SatQuoteFilters = {
            apply: applyQuoteFilters,
        };

        function matchesQuoteFilter(activeFilter, rowStatus) {
            const filter = normalizeText(activeFilter);
            const status = normalizeText(rowStatus);

            if (filter === 'todos') {
                return true;
            }

            if (filter === 'en_proceso') {
                return ['en_proceso', 'pendiente_pago', 'en_descarga', 'simulada'].includes(status);
            }

            return status === filter;
        }

        function applyQuoteFilters() {
            const rows = document.querySelectorAll('[data-quote-row]');
            const activeButton = document.querySelector('[data-quote-filter].is-active');
            const activeFilter = activeButton ? String(activeButton.getAttribute('data-quote-filter') || 'todos') : 'todos';
            const term = normalizeText(quoteSearchInput ? quoteSearchInput.value : '');
            let visible = 0;

            rows.forEach((row) => {
                const searchIndex = normalizeText(row.getAttribute('data-search') || '');
                const rowStatus = normalizeText(row.getAttribute('data-status') || '');
                const matchesSearch = term === '' || searchIndex.includes(term);
                const matchesFilter = matchesQuoteFilter(activeFilter, rowStatus);
                const shouldShow = matchesSearch && matchesFilter;

                row.style.display = shouldShow ? '' : 'none';

                if (shouldShow) {
                    visible++;
                }
            });

            if (quoteVisibleCount) {
                quoteVisibleCount.textContent = String(visible);
            }
        }

        if (quoteSearchInput) {
            quoteSearchInput.addEventListener('input', applyQuoteFilters);
        }

        quoteFilterButtons.forEach((button) => {
            button.addEventListener('click', () => {
                quoteFilterButtons.forEach((item) => item.classList.remove('is-active'));
                button.classList.add('is-active');
                applyQuoteFilters();
            });
        });

        applyQuoteFilters();
    }

    function initModalSystem() {
        const closeButtons = document.querySelectorAll('[data-rfc-close-modal]');
        const modals = document.querySelectorAll('.sat-clean-modal');

        closeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const modal = button.closest('.sat-clean-modal');
                if (modal) {
                    closeModal(modal);
                }
            });
        });

        document.addEventListener('keydown', (event) => {
            if (event.key !== 'Escape') {
                return;
            }

            const visibleModal = document.querySelector('.sat-clean-modal.is-visible');
            if (visibleModal) {
                closeModal(visibleModal);
                return;
            }

            const passwordDialog = document.getElementById('satPasswordDialog');
            if (passwordDialog && passwordDialog.classList.contains('is-visible')) {
                hidePasswordDialog();
                return;
            }

            const detailModal = document.getElementById('satQuoteDetailModal');
            if (detailModal && detailModal.classList.contains('is-visible')) {
                closeModal(detailModal);
                return;
            }

            const editModal = document.getElementById('satQuoteEditModal');
            if (editModal && editModal.classList.contains('is-visible')) {
                closeModal(editModal);
            }
        });

        modals.forEach((modal) => {
            modal.addEventListener('click', (event) => {
                if (event.target.classList.contains('sat-clean-modal')) {
                    closeModal(modal);
                }
            });
        });
    }

      function initQuoteModule() {
        ensureQuoteModal();
        restorePendingQuoteDraftFromStorage();

        const quoteButtons = document.querySelectorAll(
            '#satNewQuoteButton, #satEmptyNewQuoteButton, [aria-label="Nueva cotización"], [title="Nueva cotización"]'
        );

        quoteButtons.forEach((button) => {
            button.addEventListener('click', (event) => {
                event.preventDefault();
                clearPendingQuoteDraftStorage();
                setQuoteModalEditingState(false);
                resetQuoteModalState();
                openQuoteModal();
            });
        });

        const registerRfcButton = document.getElementById('satQuoteRegisterRfc');
        if (registerRfcButton) {
            registerRfcButton.addEventListener('click', () => {
                persistCurrentQuoteDraftToStorage({
                    reopen_after_rfc: true,
                });

                closeQuoteModal();

                const createRfcButton = document.querySelector('[data-rfc-open-modal="create"]');
                if (createRfcButton) {
                    createRfcButton.click();
                    return;
                }

                showPortalNotice('No se encontró el formulario para registrar RFC.', 'warning');
            });
        }

        const rfcSearchInput = document.getElementById('satQuoteRfcSearch');
        if (rfcSearchInput) {
            rfcSearchInput.addEventListener('input', () => {
                renderQuoteRfcOptions(rfcSearchInput.value || '');
            });
        }

        const quoteForm = document.getElementById('satQuoteForm');
        if (quoteForm) {
            quoteForm.addEventListener('submit', (event) => {
                event.preventDefault();
            });
        }

        const draftButton = document.getElementById('satQuoteDraftBtn');
        if (draftButton) {
            draftButton.addEventListener('click', async () => {
                await handleQuoteAction('draft');
            });
        }

        const simulateButton = document.getElementById('satQuoteSimulateBtn');
        if (simulateButton) {
            simulateButton.addEventListener('click', async () => {
                await handleQuoteAction('simulate');
            });
        }

        const quoteButton = document.getElementById('satQuoteConfirmBtn');
        if (quoteButton) {
            quoteButton.addEventListener('click', async () => {
                await handleQuoteAction('quote');
            });
        }

        const pdfButton = document.getElementById('satQuotePdfBtn');
        if (pdfButton) {
            pdfButton.addEventListener('click', () => {
                openQuickPdfPreview();
            });
        }

               document.addEventListener('click', (event) => {
            const editButton = event.target.closest('[data-quote-action="edit"]');
            if (editButton) {
                const row = editButton.closest('[data-quote-row="true"]');
                if (!row) return;

                const status = normalizeText(String(row.getAttribute('data-status') || '').trim());

                if (!isClientQuoteEditable(status)) {
                    showPortalNotice('Solo puedes modificar cotizaciones en proceso o borrador.', 'warning');
                    return;
                }

                prepareMainQuoteModalForEdit(row);
                openQuoteModal();
                return;
            }

            const viewButton = event.target.closest('[data-quote-action="view"]');
            if (viewButton) {
                const row = viewButton.closest('[data-quote-row="true"]');
                if (!row) return;

                populateQuoteDetailModal(row);
                const detailModal = document.getElementById('satQuoteDetailModal');
                if (detailModal) {
                    openModal(detailModal);
                }
            }
        });

        const watchedIds = [
            'satQuoteDateFrom',
            'satQuoteDateTo',
            'satQuoteXmlCount',
            'satQuoteDiscountCode',
            'satQuoteIvaRate',
            'satQuoteTipoSolicitud',
            'satQuoteSelectedRfc',
            'satQuoteSelectedRfcId',
            'satQuoteNotes',
        ];

        watchedIds.forEach((id) => {
            const element = document.getElementById(id);
            if (!element) return;

            element.addEventListener('input', updateQuoteSummaryPreview);
            element.addEventListener('change', updateQuoteSummaryPreview);
        });

        updateQuoteSummaryPreview();
    }

    function initQuoteSecondaryModals() {
        const detailCloseButtons = document.querySelectorAll('[data-quote-detail-close]');
        const editCloseButtons = document.querySelectorAll('[data-quote-edit-close]');
        const detailEditBtn = document.getElementById('satQuoteDetailEditBtn');
        const editLoadMainBtn = document.getElementById('satQuoteEditLoadMainModalBtn');

        detailCloseButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.getElementById('satQuoteDetailModal');
                if (modal) {
                    closeModal(modal);
                }
            });
        });

        editCloseButtons.forEach((button) => {
            button.addEventListener('click', () => {
                const modal = document.getElementById('satQuoteEditModal');
                if (modal) {
                    closeModal(modal);
                }
            });
        });

                if (detailEditBtn) {
            detailEditBtn.addEventListener('click', () => {
                const detailModal = document.getElementById('satQuoteDetailModal');
                const quoteId = String(detailEditBtn.getAttribute('data-quote-id') || '').trim();

                if (quoteId === '') {
                    showPortalNotice('No se encontró la cotización para editar.', 'warning');
                    return;
                }

                const row = document.querySelector(`[data-quote-row="true"][data-quote-id="${cssAttributeEscape(quoteId)}"]`);
                if (!row) {
                    showPortalNotice('No se encontró el registro de la cotización.', 'warning');
                    return;
                }

                const status = normalizeText(String(row.getAttribute('data-status') || '').trim());
                if (!isClientQuoteEditable(status)) {
                    showPortalNotice('Solo puedes modificar cotizaciones en proceso o borrador.', 'warning');
                    return;
                }

                if (detailModal) {
                    closeModal(detailModal);
                }

                prepareMainQuoteModalForEdit(row);
                openQuoteModal();
            });
        }

        if (editLoadMainBtn) {
            editLoadMainBtn.addEventListener('click', () => {
                const editPayload = buildEditModalPayload();
                if (!editPayload.ok) {
                    showPortalNotice(editPayload.message, 'warning');
                    return;
                }

                applyPayloadToMainQuoteModal(editPayload.data);

                const editModal = document.getElementById('satQuoteEditModal');
                if (editModal) {
                    closeModal(editModal);
                }

                setQuoteModalEditingState(true);
                openQuoteModal();
                showPortalNotice('Cotización cargada al cotizador.', 'success');
            });
        }
    }

    function ensureQuoteModal() {
        if (document.getElementById('satQuoteModal')) {
            injectQuoteModalStyles();
            renderQuoteRfcOptions('');
            return;
        }

        const modal = document.createElement('div');
        modal.id = 'satQuoteModal';
        modal.className = 'sat-clean-modal sat-clean-modal--quote';
        modal.setAttribute('aria-hidden', 'true');

        modal.innerHTML = `
            <div class="sat-clean-modal__backdrop" data-quote-close-modal></div>
            <div class="sat-clean-modal__dialog sat-clean-modal__dialog--xl sat-clean-quote-modal__dialog" role="dialog" aria-modal="true" aria-labelledby="satQuoteModalTitle">
                <div class="sat-clean-modal__header">
                    <div>
                        <h2 class="sat-clean-modal__title" id="satQuoteModalTitle">Nueva cotización</h2>
                        <p class="sat-clean-modal__subtitle">Selecciona un RFC, captura el rango y genera la simulación, el borrador o la cotización.</p>
                    </div>

                    <button type="button" class="sat-clean-modal__close" data-quote-close-modal aria-label="Cerrar">
                        ✕
                    </button>
                </div>

                <div class="sat-clean-modal__body-scroll">
                    <form id="satQuoteForm" class="sat-clean-modal__form" autocomplete="off">
                        <input type="hidden" id="satQuoteDraftId" value="">
                        <input type="hidden" id="satQuoteSelectedRfcId" value="">
                        <input type="hidden" id="satQuoteSelectedRfc" value="">
                        <input type="hidden" id="satQuoteSelectedRazonSocial" value="">

                        <section class="sat-clean-form-section">
                            <div class="sat-clean-form-section__head">
                                <h3 class="sat-clean-form-section__title">RFC para cotizar</h3>
                                <p class="sat-clean-form-section__text">Busca un RFC existente o registra uno nuevo sin salir del flujo.</p>
                            </div>

                            <div class="sat-clean-quote-rfc-picker">
                                <div class="sat-clean-quote-rfc-picker__top">
                                    <div class="sat-clean-form-field sat-clean-form-field--full">
                                        <label for="satQuoteRfcSearch">Buscar RFC</label>
                                        <input
                                            type="text"
                                            id="satQuoteRfcSearch"
                                            placeholder="Buscar por RFC o razón social"
                                        >
                                    </div>

                                    <div class="sat-clean-quote-rfc-picker__actions">
                                        <button
                                            type="button"
                                            class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact"
                                            id="satQuoteRegisterRfc"
                                        >
                                            Registrar RFC
                                        </button>
                                    </div>
                                </div>

                                <div class="sat-clean-quote-rfc-picker__selected" id="satQuoteSelectedBadge">
                                    Ningún RFC seleccionado
                                </div>

                                <div class="sat-clean-quote-rfc-picker__helper">
                                    Si el RFC no existe en el listado, usa “Registrar RFC”, guarda el nuevo registro y al volver se recuperará esta cotización.
                                </div>

                                <div class="sat-clean-quote-rfc-picker__list" id="satQuoteRfcList"></div>
                            </div>
                        </section>

                        <section class="sat-clean-form-section">
                            <div class="sat-clean-form-section__head">
                                <h3 class="sat-clean-form-section__title">Datos de la solicitud</h3>
                            </div>

                            <div class="sat-clean-form-grid sat-clean-form-grid--3">
                                <div class="sat-clean-form-field">
                                    <label for="satQuoteTipoSolicitud">Tipo de solicitud</label>
                                    <select id="satQuoteTipoSolicitud">
                                        <option value="emitidos">Emitidos</option>
                                        <option value="recibidos">Recibidos</option>
                                        <option value="ambos">Ambos</option>
                                    </select>
                                </div>

                                <div class="sat-clean-form-field">
                                    <label for="satQuoteDateFrom">Fecha inicial</label>
                                    <input type="date" id="satQuoteDateFrom">
                                </div>

                                <div class="sat-clean-form-field">
                                    <label for="satQuoteDateTo">Fecha final</label>
                                    <input type="date" id="satQuoteDateTo">
                                </div>

                                <div class="sat-clean-form-field">
                                    <label for="satQuoteXmlCount">XML estimados</label>
                                    <input type="number" id="satQuoteXmlCount" min="1" step="1" placeholder="Ej. 1500">
                                </div>

                                <div class="sat-clean-form-field">
                                    <label for="satQuoteDiscountCode">Código de descuento</label>
                                    <input type="text" id="satQuoteDiscountCode" maxlength="64" placeholder="Opcional">
                                </div>

                                <div class="sat-clean-form-field">
                                    <label for="satQuoteIvaRate">IVA %</label>
                                    <input type="number" id="satQuoteIvaRate" min="0" max="100" step="0.01" value="16">
                                </div>

                                <div class="sat-clean-form-field sat-clean-form-field--full">
                                    <label for="satQuoteNotes">Notas</label>
                                    <textarea id="satQuoteNotes" rows="3" placeholder="Notas internas o contexto de la cotización"></textarea>
                                </div>
                            </div>
                        </section>

                        <section class="sat-clean-form-section sat-clean-form-section--soft">
                            <div class="sat-clean-form-section__head">
                                <h3 class="sat-clean-form-section__title">Resumen</h3>
                                <p class="sat-clean-form-section__text">Vista previa antes de simular, guardar borrador o cotizar.</p>
                            </div>

                            <div class="sat-clean-quote-resume" id="satQuoteResume">
                                <div class="sat-clean-quote-resume__grid">
                                    <div class="sat-clean-quote-resume__item">
                                        <span class="sat-clean-quote-resume__label">RFC</span>
                                        <strong class="sat-clean-quote-resume__value" id="satQuoteResumeRfc">Pendiente</strong>
                                    </div>

                                    <div class="sat-clean-quote-resume__item">
                                        <span class="sat-clean-quote-resume__label">Tipo</span>
                                        <strong class="sat-clean-quote-resume__value" id="satQuoteResumeTipo">Emitidos</strong>
                                    </div>

                                    <div class="sat-clean-quote-resume__item">
                                        <span class="sat-clean-quote-resume__label">Periodo</span>
                                        <strong class="sat-clean-quote-resume__value" id="satQuoteResumePeriodo">Sin definir</strong>
                                    </div>

                                    <div class="sat-clean-quote-resume__item">
                                        <span class="sat-clean-quote-resume__label">XML estimados</span>
                                        <strong class="sat-clean-quote-resume__value" id="satQuoteResumeXml">0</strong>
                                    </div>
                                </div>

                                <div class="sat-clean-quote-result" id="satQuoteResultBox">
                                    <div class="sat-clean-quote-result__placeholder">
                                        Aquí se mostrará el cálculo de la simulación o cotización.
                                    </div>
                                </div>
                            </div>
                        </section>

                        <div class="sat-clean-modal__actions sat-clean-modal__actions--spread">
                            <div class="sat-clean-quote-modal__left-actions">
                                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" data-quote-close-modal>
                                    Cancelar
                                </button>
                            </div>

                            <div class="sat-clean-quote-modal__right-actions">
                                <button type="button" class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact" id="satQuotePdfBtn">
                                    PDF simulación
                                </button>

                                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" id="satQuoteSimulateBtn">
                                    Simular cotización
                                </button>

                                <button type="button" class="sat-clean-btn sat-clean-btn--ghost" id="satQuoteDraftBtn">
                                    Guardar borrador
                                </button>

                                <button type="button" class="sat-clean-btn sat-clean-btn--primary" id="satQuoteConfirmBtn">
                                    Cotizar
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        `;

        document.body.appendChild(modal);
        injectQuoteModalStyles();
        bindQuoteModalEvents();
        renderQuoteRfcOptions('');
    }

    function bindQuoteModalEvents() {
        const modal = document.getElementById('satQuoteModal');
        if (!modal) {
            return;
        }

        const closeButtons = modal.querySelectorAll('[data-quote-close-modal]');

        closeButtons.forEach((button) => {
            button.addEventListener('click', () => {
                closeQuoteModal();
            });
        });

        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeQuoteModal();
            }
        });
    }

    function openQuoteModal() {
        const modal = document.getElementById('satQuoteModal');
        if (!modal) {
            showPortalNotice('No se encontró el modal de cotización.', 'warning');
            return;
        }

        renderQuoteRfcOptions('');
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sat-clean-modal-open');

        const rfcSearchInput = document.getElementById('satQuoteRfcSearch');
        if (rfcSearchInput) {
            window.requestAnimationFrame(() => {
                rfcSearchInput.focus();
            });
        }

        updateQuoteSummaryPreview();
    }

    function closeQuoteModal() {
        const modal = document.getElementById('satQuoteModal');
        if (!modal) {
            return;
        }

        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');

        const hasVisibleModal = document.querySelector('.sat-clean-modal.is-visible');
        const passwordDialogVisible = document.getElementById('satPasswordDialog')?.classList.contains('is-visible');

        if (!hasVisibleModal && !passwordDialogVisible) {
            document.body.classList.remove('sat-clean-modal-open');
        }
    }

      function resetQuoteModalState(options = {}) {
        const preserveRfc = options.preserveRfc === true;

        const draftIdInput = document.getElementById('satQuoteDraftId');
        const selectedRfcIdInput = document.getElementById('satQuoteSelectedRfcId');
        const selectedRfcInput = document.getElementById('satQuoteSelectedRfc');
        const selectedRazonSocialInput = document.getElementById('satQuoteSelectedRazonSocial');
        const selectedBadge = document.getElementById('satQuoteSelectedBadge');
        const rfcSearchInput = document.getElementById('satQuoteRfcSearch');
        const tipoInput = document.getElementById('satQuoteTipoSolicitud');
        const dateFromInput = document.getElementById('satQuoteDateFrom');
        const dateToInput = document.getElementById('satQuoteDateTo');
        const xmlCountInput = document.getElementById('satQuoteXmlCount');
        const discountInput = document.getElementById('satQuoteDiscountCode');
        const ivaInput = document.getElementById('satQuoteIvaRate');
        const notesInput = document.getElementById('satQuoteNotes');
        const resultBox = document.getElementById('satQuoteResultBox');

        if (draftIdInput) {
            draftIdInput.value = '';
        }

        if (!preserveRfc) {
            if (selectedRfcIdInput) selectedRfcIdInput.value = '';
            if (selectedRfcInput) selectedRfcInput.value = '';
            if (selectedRazonSocialInput) selectedRazonSocialInput.value = '';
            if (selectedBadge) selectedBadge.textContent = 'Ningún RFC seleccionado';
        }

        if (rfcSearchInput) rfcSearchInput.value = '';
        if (tipoInput) tipoInput.value = 'emitidos';
        if (dateFromInput) dateFromInput.value = '';
        if (dateToInput) dateToInput.value = '';
        if (xmlCountInput) xmlCountInput.value = '';
        if (discountInput) discountInput.value = '';
        if (ivaInput) ivaInput.value = '16';
        if (notesInput) notesInput.value = '';

        if (resultBox) {
            resultBox.innerHTML = `
                <div class="sat-clean-quote-result__placeholder">
                    Aquí se mostrará el cálculo de la simulación o cotización.
                </div>
            `;
        }

        renderQuoteRfcOptions('');
        updateQuoteSummaryPreview();
    }

        function loadQuoteRowIntoModal(row) {
        const draftId = String(row.getAttribute('data-quote-id') || '').trim();
        const rfcId = String(row.getAttribute('data-rfc-id') || '').trim();
        const rfc = String(row.getAttribute('data-rfc') || '').trim().toUpperCase();
        const razonSocial = decodeHtmlEntities(String(row.getAttribute('data-razon-social') || '').trim());
        const tipo = String(row.getAttribute('data-tipo') || 'emitidos').trim().toLowerCase();
        const dateFrom = String(row.getAttribute('data-date-from') || '').trim();
        const dateTo = String(row.getAttribute('data-date-to') || '').trim();
        const total = String(row.getAttribute('data-total') || '').trim();
        const progress = String(row.getAttribute('data-progress') || '').trim();
        const status = String(row.getAttribute('data-status') || '').trim();
        const concepto = decodeHtmlEntities(String(row.getAttribute('data-concepto') || '').trim());
        const xmlCount = String(row.getAttribute('data-xml-count') || '').trim();
        const discountCode = decodeHtmlEntities(String(row.getAttribute('data-discount-code') || '').trim());
        const ivaRate = String(row.getAttribute('data-iva-rate') || '16').trim();
        const notes = decodeHtmlEntities(String(row.getAttribute('data-notes') || '').trim());

        const draftIdInput = document.getElementById('satQuoteDraftId');
        const selectedRfcIdInput = document.getElementById('satQuoteSelectedRfcId');
        const selectedRfcInput = document.getElementById('satQuoteSelectedRfc');
        const selectedRazonSocialInput = document.getElementById('satQuoteSelectedRazonSocial');
        const selectedBadge = document.getElementById('satQuoteSelectedBadge');
        const tipoInput = document.getElementById('satQuoteTipoSolicitud');
        const dateFromInput = document.getElementById('satQuoteDateFrom');
        const dateToInput = document.getElementById('satQuoteDateTo');
        const xmlCountInput = document.getElementById('satQuoteXmlCount');
        const discountInput = document.getElementById('satQuoteDiscountCode');
        const notesInput = document.getElementById('satQuoteNotes');
        const ivaInput = document.getElementById('satQuoteIvaRate');
        const resultBox = document.getElementById('satQuoteResultBox');

        if (draftIdInput) draftIdInput.value = draftId;
        if (selectedRfcIdInput) selectedRfcIdInput.value = rfcId;
        if (selectedRfcInput) selectedRfcInput.value = rfc;
        if (selectedRazonSocialInput) selectedRazonSocialInput.value = razonSocial;
        if (selectedBadge) selectedBadge.textContent = razonSocial !== '' ? `${rfc} · ${razonSocial}` : rfc;
        if (tipoInput) tipoInput.value = ['emitidos', 'recibidos', 'ambos'].includes(tipo) ? tipo : 'emitidos';
        if (dateFromInput) dateFromInput.value = normalizeDateForInput(dateFrom);
        if (dateToInput) dateToInput.value = normalizeDateForInput(dateTo);
        if (xmlCountInput) xmlCountInput.value = xmlCount !== '' ? xmlCount : '';
        if (discountInput) discountInput.value = discountCode;
        if (notesInput) notesInput.value = notes !== '' ? notes : concepto;
        if (ivaInput) ivaInput.value = ivaRate !== '' ? ivaRate : '16';

        if (resultBox) {
            renderQuoteResult({
                mode: status === 'borrador' ? 'draft' : 'quote',
                simulated: false,
                data: {
                    folio: row.getAttribute('data-folio') || '',
                    total,
                    progress,
                    rfc_id: rfcId,
                    rfc,
                    razon_social: razonSocial,
                    tipo,
                    date_from: normalizeDateForInput(dateFrom),
                    date_to: normalizeDateForInput(dateTo),
                    concepto,
                    xml_count: xmlCount,
                    discount_code: discountCode,
                    iva_rate: ivaRate,
                    notes,
                },
                warning: '',
            });
        }

        renderQuoteRfcOptions('');
        updateQuoteSummaryPreview();
    }

        function getAvailableRfcs() {
        const map = new Map();

        document.querySelectorAll('[data-rfc-row="true"]').forEach((row) => {
            const rfc = String(
                row.querySelector('.sat-clean-rfc-inline-main__rfc')?.textContent || ''
            ).trim().toUpperCase();

            const razonSocial = String(
                row.querySelector('.sat-clean-rfc-inline-text')?.textContent || ''
            ).trim();

            const id =
                String(row.getAttribute('data-rfc-id') || '').trim() ||
                String(row.dataset.rfcId || '').trim() ||
                '';

            if (rfc !== '') {
                map.set(rfc, {
                    id,
                    rfc,
                    razon_social: razonSocial,
                });
            }
        });

        const globalSat = window.P360_SAT || {};
        const optionSources = Array.isArray(globalSat.rfcOptions) ? globalSat.rfcOptions : [];

        optionSources.forEach((item) => {
            const rfc = String(item?.rfc || item?.value || '').trim().toUpperCase();
            const razonSocial = String(item?.razon_social || item?.label || item?.text || '').trim();
            const id = String(item?.id || item?.rfc_id || item?.uuid || '').trim();

            if (rfc !== '') {
                map.set(rfc, {
                    id,
                    rfc,
                    razon_social: razonSocial,
                });
            }
        });

        return Array.from(map.values()).sort((a, b) => {
            return a.rfc.localeCompare(b.rfc, 'es');
        });
    }

    function renderQuoteRfcOptions(searchTerm = '') {
        const list = document.getElementById('satQuoteRfcList');
        if (!list) {
            return;
        }

        const items = getAvailableRfcs();
        const term = normalizeText(searchTerm);
        const filtered = items.filter((item) => {
            const haystack = normalizeText(`${item.rfc} ${item.razon_social}`);
            return term === '' || haystack.includes(term);
        });

        if (!filtered.length) {
            list.innerHTML = `
                <div class="sat-clean-quote-rfc-picker__empty">
                    No se encontraron RFC con ese criterio.
                </div>
            `;
            return;
        }

        const selectedRfc = String(document.getElementById('satQuoteSelectedRfc')?.value || '').trim().toUpperCase();

        list.innerHTML = filtered.map((item) => {
            const isSelected = selectedRfc !== '' && selectedRfc === item.rfc;

            return `
                <button
                    type="button"
                    class="sat-clean-quote-rfc-option ${isSelected ? 'is-selected' : ''}"
                    data-quote-rfc-option="true"
                    data-rfc-id="${escapeHtml(item.id || '')}"
                    data-rfc="${escapeHtml(item.rfc)}"
                    data-razon-social="${escapeHtml(item.razon_social || '')}"
                >
                    <span class="sat-clean-quote-rfc-option__main">${escapeHtml(item.rfc)}</span>
                    <span class="sat-clean-quote-rfc-option__sub">${escapeHtml(item.razon_social || 'Sin razón social')}</span>
                </button>
            `;
        }).join('');

        list.querySelectorAll('[data-quote-rfc-option="true"]').forEach((button) => {
            button.addEventListener('click', () => {
                const rfcId = String(button.getAttribute('data-rfc-id') || '').trim();
                const rfc = String(button.getAttribute('data-rfc') || '').trim().toUpperCase();
                const razonSocial = decodeHtmlEntities(String(button.getAttribute('data-razon-social') || '').trim());

                const selectedRfcIdInput = document.getElementById('satQuoteSelectedRfcId');
                const selectedRfcInput = document.getElementById('satQuoteSelectedRfc');
                const selectedRazonSocialInput = document.getElementById('satQuoteSelectedRazonSocial');
                const selectedBadge = document.getElementById('satQuoteSelectedBadge');

                if (selectedRfcIdInput) {
                    selectedRfcIdInput.value = rfcId;
                }

                if (selectedRfcInput) {
                    selectedRfcInput.value = rfc;
                }

                if (selectedRazonSocialInput) {
                    selectedRazonSocialInput.value = razonSocial;
                }

                renderQuoteRfcOptions(document.getElementById('satQuoteRfcSearch')?.value || '');
                updateQuoteSummaryPreview();
            });
        });
    }

    function updateQuoteSummaryPreview() {
        const selectedRfc = String(document.getElementById('satQuoteSelectedRfc')?.value || '').trim();
        const selectedRazonSocial = String(document.getElementById('satQuoteSelectedRazonSocial')?.value || '').trim();
        const tipo = String(document.getElementById('satQuoteTipoSolicitud')?.value || 'emitidos').trim();
        const from = String(document.getElementById('satQuoteDateFrom')?.value || '').trim();
        const to = String(document.getElementById('satQuoteDateTo')?.value || '').trim();
        const xmlCount = String(document.getElementById('satQuoteXmlCount')?.value || '0').trim();

        const rfcResume = document.getElementById('satQuoteResumeRfc');
        const tipoResume = document.getElementById('satQuoteResumeTipo');
        const periodoResume = document.getElementById('satQuoteResumePeriodo');
        const xmlResume = document.getElementById('satQuoteResumeXml');

        if (rfcResume) {
            rfcResume.textContent = selectedRfc !== ''
                ? (selectedRazonSocial !== '' ? `${selectedRfc} · ${selectedRazonSocial}` : selectedRfc)
                : 'Pendiente';
        }

        if (tipoResume) {
            tipoResume.textContent = formatTipoSolicitudLabel(tipo);
        }

        if (periodoResume) {
            periodoResume.textContent = (from !== '' && to !== '')
                ? `${formatShortDate(from)} al ${formatShortDate(to)}`
                : 'Sin definir';
        }

        if (xmlResume) {
            xmlResume.textContent = xmlCount !== '' ? xmlCount : '0';
        }
    }

        async function handleQuoteAction(mode) {
        const payload = buildQuotePayload();

        if (!payload.ok) {
            showPortalNotice(payload.message, 'warning');
            return;
        }

        const draftId = String(document.getElementById('satQuoteDraftId')?.value || '').trim();
        const buttonMap = {
            draft: document.getElementById('satQuoteDraftBtn'),
            simulate: document.getElementById('satQuoteSimulateBtn'),
            quote: document.getElementById('satQuoteConfirmBtn'),
        };

        const button = buttonMap[mode] || null;
        const endpoint = resolveQuoteEndpoint(mode);

        if (!endpoint) {
            showPortalNotice('No se encontró la ruta de cotización.', 'warning');
            return;
        }

        const originalText = button ? button.textContent : '';
        if (button) {
            button.disabled = true;
            button.textContent =
                mode === 'draft'
                    ? 'Guardando...'
                    : mode === 'simulate'
                        ? 'Recalculando...'
                        : 'Cotizando...';
        }

        const resultBox = document.getElementById('satQuoteResultBox');
        if (resultBox) {
            resultBox.innerHTML = `
                <div class="sat-clean-quote-result__loading">
                    Procesando solicitud...
                </div>
            `;
        }

        try {
            const requestBody = {
                mode,
                draft_id: draftId !== '' ? draftId : null,
                rfc_id: payload.data.rfc_id,
                rfc: payload.data.rfc,
                razon_social: payload.data.razon_social,
                tipo: payload.data.tipo,
                date_from: payload.data.date_from,
                date_to: payload.data.date_to,
                xml_count: payload.data.xml_count,
                xml_count_estimated: payload.data.xml_count,
                discount_code: payload.data.discount_code,
                iva_rate: payload.data.iva_rate,
                iva: payload.data.iva_rate,
                notes: payload.data.notes,
            };

            const response = await fetch(endpoint, {
                method: 'POST',
                headers: buildAjaxHeaders(),
                credentials: 'same-origin',
                body: JSON.stringify(requestBody),
            });

            const json = await response.json().catch(() => null);

            if (!response.ok || !json || json.ok !== true) {
                const message = json && (json.msg || json.message)
                    ? (json.msg || json.message)
                    : 'No se pudo procesar la cotización.';

                renderQuoteResult({
                    mode,
                    simulated: false,
                    data: payload.data,
                    warning: message,
                });

                showPortalNotice(message, 'warning');
                return;
            }

            const resultData = json.data || {};

            renderQuoteResult({
                mode,
                simulated: mode === 'simulate',
                data: resultData,
                warning:
                    mode === 'quote'
                        ? 'Solicitud registrada y enviada a soporte.'
                        : '',
            });

            const savedId = String(resultData?.id || resultData?.draft_id || draftId || '').trim();
            const draftIdInput = document.getElementById('satQuoteDraftId');
            if (draftIdInput && savedId !== '') {
                draftIdInput.value = savedId;
            }

            upsertQuoteRow({
                ...payload.data,
                ...resultData,
                id: savedId !== '' ? savedId : (resultData?.id || ''),
                draft_id: savedId !== '' ? savedId : (resultData?.draft_id || ''),
            });

            if (mode === 'simulate') {
                showPortalNotice('Recalculo generado correctamente.', 'success');
                return;
            }

            if (mode === 'draft') {
                showPortalNotice(json.msg || 'Cotización actualizada correctamente.', 'success');
                return;
            }

            closeQuoteModal();
            resetQuoteModalState();
            setQuoteModalEditingState(false);
            showPortalNotice(json.msg || 'Cotización solicitada correctamente.', 'success');
        } catch (error) {
            console.error('[SAT Portal] Error procesando cotización:', error);

            renderQuoteResult({
                mode,
                simulated: false,
                data: payload.data,
                warning: 'Ocurrió un error al procesar la solicitud.',
            });

            showPortalNotice('Ocurrió un error al procesar la cotización.', 'warning');
        } finally {
            if (button) {
                button.disabled = false;
                button.textContent = originalText;
            }
        }
    }

        function isClientQuoteEditable(status) {
        const normalized = normalizeText(status);

        return normalized === 'en_proceso' || normalized === 'borrador';
    }

    function setQuoteModalEditingState(isEditing) {
        const title = document.getElementById('satQuoteModalTitle');
        const subtitle = document.querySelector('#satQuoteModal .sat-clean-modal__subtitle');
        const draftBtn = document.getElementById('satQuoteDraftBtn');
        const simulateBtn = document.getElementById('satQuoteSimulateBtn');
        const confirmBtn = document.getElementById('satQuoteConfirmBtn');
        const pdfBtn = document.getElementById('satQuotePdfBtn');

        if (title) {
            title.textContent = isEditing ? 'Editar cotización' : 'Nueva cotización';
        }

        if (subtitle) {
            subtitle.textContent = isEditing
                ? 'Modifica la cotización en proceso, recalcula y vuelve a guardar o recotizar.'
                : 'Selecciona un RFC, captura el rango y genera la simulación, el borrador o la cotización.';
        }

        if (draftBtn) {
            draftBtn.textContent = isEditing ? 'Guardar cambios' : 'Guardar borrador';
        }

        if (simulateBtn) {
            simulateBtn.textContent = isEditing ? 'Recalcular cotización' : 'Simular cotización';
        }

        if (confirmBtn) {
            confirmBtn.textContent = isEditing ? 'Recotizar' : 'Cotizar';
        }

        if (pdfBtn) {
            pdfBtn.textContent = isEditing ? 'PDF recálculo' : 'PDF simulación';
        }
    }

    function prepareMainQuoteModalForEdit(row) {
        const quoteModal = document.getElementById('satQuoteModal');
        const editModal = document.getElementById('satQuoteEditModal');

        if (editModal && editModal.classList.contains('is-visible')) {
            closeModal(editModal);
        }

        if (!quoteModal) {
            ensureQuoteModal();
        }

        setQuoteModalEditingState(true);
        resetQuoteModalState({ preserveRfc: false });
        loadQuoteRowIntoModal(row);
        updateQuoteSummaryPreview();
    }

    function buildQuotePayload() {
        const rfcId = String(document.getElementById('satQuoteSelectedRfcId')?.value || '').trim();
        const rfc = String(document.getElementById('satQuoteSelectedRfc')?.value || '').trim().toUpperCase();
        const razonSocial = String(document.getElementById('satQuoteSelectedRazonSocial')?.value || '').trim();
        const tipo = String(document.getElementById('satQuoteTipoSolicitud')?.value || 'emitidos').trim();
        const dateFrom = String(document.getElementById('satQuoteDateFrom')?.value || '').trim();
        const dateTo = String(document.getElementById('satQuoteDateTo')?.value || '').trim();
        const xmlCountRaw = String(document.getElementById('satQuoteXmlCount')?.value || '').trim();
        const discountCode = String(document.getElementById('satQuoteDiscountCode')?.value || '').trim();
        const ivaRateRaw = String(document.getElementById('satQuoteIvaRate')?.value || '16').trim();
        const notes = String(document.getElementById('satQuoteNotes')?.value || '').trim();

        if (rfc === '') {
            return { ok: false, message: 'Debes seleccionar un RFC para cotizar.' };
        }

        if (dateFrom === '' || dateTo === '') {
            return { ok: false, message: 'Debes capturar la fecha inicial y final.' };
        }

        if (dateFrom > dateTo) {
            return { ok: false, message: 'La fecha inicial no puede ser mayor a la final.' };
        }

        const xmlCount = parseInt(xmlCountRaw || '0', 10);
        if (!Number.isFinite(xmlCount) || xmlCount <= 0) {
            return { ok: false, message: 'Debes capturar una cantidad estimada de XML válida.' };
        }

        const ivaRate = parseFloat(ivaRateRaw || '16');
        if (!Number.isFinite(ivaRate) || ivaRate < 0 || ivaRate > 100) {
            return { ok: false, message: 'El IVA capturado no es válido.' };
        }

        return {
            ok: true,
            data: {
                rfc_id: rfcId !== '' ? rfcId : null,
                rfc,
                razon_social: razonSocial,
                tipo,
                date_from: dateFrom,
                date_to: dateTo,
                xml_count: xmlCount,
                discount_code: discountCode,
                iva_rate: ivaRate,
                notes,
            },
        };
    }

    function resolveQuoteEndpoint(mode) {
        const sat = window.P360_SAT || {};
        const body = document.body;

        const endpointCandidates = mode === 'simulate'
            ? [
                sat.quickCalcUrl,
                sat.quick_calc_url,
                sat.quickQuoteUrl,
                sat.quoteSimulateUrl,
                body?.dataset?.satQuickCalcUrl,
                body?.dataset?.satQuickQuoteUrl,
            ]
            : [
                sat.quoteCalcUrl,
                sat.quote_calc_url,
                sat.quoteRequestUrl,
                sat.quoteCreateUrl,
                body?.dataset?.satQuoteCalcUrl,
                body?.dataset?.satQuoteRequestUrl,
            ];

        const endpoint = endpointCandidates.find((value) => typeof value === 'string' && value.trim() !== '');
        return endpoint ? String(endpoint).trim() : '';
    }

    function resolveQuickPdfEndpoint() {
            const sat = window.P360_SAT || {};
            const body = document.body;

            const candidates = [
                sat.quickPdfUrl,
                sat.quick_pdf_url,
                body?.dataset?.satQuickPdfUrl,
            ];

            const endpoint = candidates.find((value) => typeof value === 'string' && value.trim() !== '');
            return endpoint ? String(endpoint).trim() : '';
        }

        async function openQuickPdfPreview() {
        const payload = buildQuotePayload();

        if (!payload.ok) {
            showPortalNotice(payload.message, 'warning');
            return;
        }

        const endpoint = resolveQuickPdfEndpoint();
        if (!endpoint) {
            showPortalNotice('No se encontró la ruta del PDF de simulación.', 'warning');
            return;
        }

        const pdfButton = document.getElementById('satQuotePdfBtn');
        const originalHtml = pdfButton ? pdfButton.innerHTML : '';
        const originalDisabled = pdfButton ? pdfButton.disabled : false;

        if (pdfButton) {
            pdfButton.disabled = true;
            pdfButton.classList.add('is-loading');
            pdfButton.innerHTML = `
                <span class="sat-clean-btn__spinner" aria-hidden="true"></span>
                <span>Generando PDF...</span>
            `;
        }

        try {
            const url = new URL(endpoint, window.location.origin);
            url.searchParams.set('quote_mode', 'simulation');
            url.searchParams.set('rfc_id', String(payload.data.rfc_id || ''));
            url.searchParams.set('rfc', payload.data.rfc);
            url.searchParams.set('tipo', payload.data.tipo);
            url.searchParams.set('date_from', payload.data.date_from);
            url.searchParams.set('date_to', payload.data.date_to);
            url.searchParams.set('xml_count', String(payload.data.xml_count));
            url.searchParams.set('xml_count_estimated', String(payload.data.xml_count));
            url.searchParams.set('discount_code', payload.data.discount_code);
            url.searchParams.set('iva_rate', String(payload.data.iva_rate));
            url.searchParams.set('iva', String(payload.data.iva_rate));
            url.searchParams.set('notes', payload.data.notes || '');

            const response = await fetch(url.toString(), {
                method: 'GET',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/pdf,application/json,text/html;q=0.9,*/*;q=0.8',
                },
                credentials: 'same-origin',
            });

            if (!response.ok) {
                let errorMessage = 'No se pudo generar el PDF de simulación.';

                try {
                    const json = await response.clone().json();
                    if (json?.msg || json?.message) {
                        errorMessage = json.msg || json.message;
                    }
                } catch (_) {
                    // ignorar
                }

                throw new Error(errorMessage);
            }

            const blob = await response.blob();
            if (!blob || blob.size === 0) {
                throw new Error('El PDF se generó vacío.');
            }

            const blobUrl = window.URL.createObjectURL(blob);
            window.open(blobUrl, '_blank', 'noopener');

            window.setTimeout(() => {
                window.URL.revokeObjectURL(blobUrl);
            }, 60000);

            showPortalNotice('PDF generado correctamente.', 'success');
        } catch (error) {
            console.error('[SAT Portal] Error generando PDF:', error);
            showPortalNotice(error.message || 'Ocurrió un error al generar el PDF.', 'warning');
        } finally {
            if (pdfButton) {
                pdfButton.disabled = originalDisabled;
                pdfButton.classList.remove('is-loading');
                pdfButton.innerHTML = originalHtml;
            }
        }
    }

    function renderQuoteResult({ mode, simulated, data, warning = '' }) {
        const resultBox = document.getElementById('satQuoteResultBox');
        if (!resultBox) {
            return;
        }

        const folio = String(data?.folio || 'SIN-FOLIO').trim();
        const base = toMoneyValue(data?.base);
        const subtotal = toMoneyValue(data?.subtotal);
        const ivaAmount = toMoneyValue(data?.iva_amount);
        const total = toMoneyValue(data?.total);
        const xmlCount = parseInt(data?.xml_count || data?.xmlCount || 0, 10) || 0;
        const discountCode = String(data?.discount_code_applied || data?.discount_code || '').trim();
        const discountLabel = String(data?.discount_label || '').trim();
        const note = String(data?.note || '').trim();
        const validUntil = String(data?.valid_until || '').trim();

        resultBox.innerHTML = `
            <div class="sat-clean-quote-result__card ${simulated ? 'is-simulated' : ''}">
                <div class="sat-clean-quote-result__head">
                    <div>
                        <div class="sat-clean-quote-result__mode">
                            ${
                                mode === 'simulate'
                                    ? 'Simulación de cotización'
                                    : mode === 'draft'
                                        ? 'Borrador de cotización'
                                        : 'Cotización'
                            }
                        </div>
                        <div class="sat-clean-quote-result__folio">${escapeHtml(folio)}</div>
                    </div>
                    ${simulated ? '<div class="sat-clean-quote-result__watermark">SIN VALIDEZ</div>' : ''}
                </div>

                <div class="sat-clean-quote-result__grid">
                    <div class="sat-clean-quote-result__item">
                        <span>XML estimados</span>
                        <strong>${numberFormat(xmlCount)}</strong>
                    </div>
                    <div class="sat-clean-quote-result__item">
                        <span>Base</span>
                        <strong>${formatMoney(base)}</strong>
                    </div>
                    <div class="sat-clean-quote-result__item">
                        <span>Subtotal</span>
                        <strong>${formatMoney(subtotal)}</strong>
                    </div>
                    <div class="sat-clean-quote-result__item">
                        <span>IVA</span>
                        <strong>${formatMoney(ivaAmount)}</strong>
                    </div>
                    <div class="sat-clean-quote-result__item is-total">
                        <span>Total</span>
                        <strong>${formatMoney(total)}</strong>
                    </div>
                    <div class="sat-clean-quote-result__item">
                        <span>Vigencia</span>
                        <strong>${validUntil !== '' ? escapeHtml(validUntil) : 'N/D'}</strong>
                    </div>
                </div>

                ${discountCode !== '' || discountLabel !== '' ? `
                    <div class="sat-clean-quote-result__discount">
                        <strong>Descuento:</strong>
                        ${escapeHtml(discountLabel !== '' ? discountLabel : discountCode)}
                    </div>
                ` : ''}

                ${note !== '' ? `
                    <div class="sat-clean-quote-result__note">
                        ${escapeHtml(note)}
                    </div>
                ` : ''}

                ${warning !== '' ? `
                    <div class="sat-clean-quote-result__warning">
                        ${escapeHtml(warning)}
                    </div>
                ` : ''}
            </div>
        `;
    }

    function createLocalQuoteSimulation(payload, mode) {
        const xmlCount = parseInt(payload.xml_count || 0, 10) || 0;
        const base = Math.max(0, xmlCount * 0.45);
        const discountPct = payload.discount_code !== '' ? 10 : 0;
        const subtotal = base - (base * (discountPct / 100));
        const ivaAmount = subtotal * ((parseFloat(payload.iva_rate || 16) || 16) / 100);
        const total = subtotal + ivaAmount;

        return {
            folio: `SIM-${Date.now().toString().slice(-6)}`,
            xml_count: xmlCount,
            base,
            subtotal,
            iva_amount: ivaAmount,
            total,
            valid_until: new Date(Date.now() + (7 * 24 * 60 * 60 * 1000)).toISOString().slice(0, 10),
            discount_code: payload.discount_code,
            discount_code_applied: payload.discount_code,
            discount_label: payload.discount_code !== '' ? `Código ${payload.discount_code}` : '',
            note: mode === 'simulate'
                ? 'Simulación local generada sin validez comercial.'
                : 'Cálculo local generado mientras se integra el flujo final.',
        };
    }

    function upsertQuoteRow(data) {
        const quoteId = String(data?.id || data?.draft_id || '').trim();
        const tbody = document.getElementById('satQuotesTableBody')
            || document.querySelector('#satQuotesTable tbody')
            || document.querySelector('[aria-label="Cotizaciones SAT"] tbody');

        if (!tbody) {
            return;
        }

        const emptyRow = document.getElementById('satQuotesEmptyRow');
        if (emptyRow) {
            emptyRow.remove();
        } else {
            const existingEmpty = tbody.querySelector('.sat-clean-empty-state');
            if (existingEmpty) {
                existingEmpty.closest('tr')?.remove();
            }
        }

        const existingRow = quoteId !== ''
            ? document.querySelector(`#satQuoteRow-${cssEscapeSimple(quoteId)}`)
            : null;

        if (existingRow) {
            updateQuoteRow(existingRow, data);
        } else {
            const row = buildQuoteRow(data);
            tbody.prepend(row);
        }

        if (window.P360SatQuoteFilters && typeof window.P360SatQuoteFilters.apply === 'function') {
            window.P360SatQuoteFilters.apply();
        }

        ensureActionTooltips();
    }

    function buildQuoteRow(data) {
        const quoteId = String(data?.id || data?.draft_id || '').trim();
        const folio = String(data?.folio || `COT-${Date.now().toString().slice(-6)}`).trim();
        const rfc = String(data?.rfc || '').trim().toUpperCase();
        const razonSocial = String(data?.razon_social || '').trim();
        const concepto = buildQuoteConceptoFromData(data);
        const status = normalizeText(String(data?.status || 'borrador'));
        const statusLabel = String(data?.status_label || mapQuoteStatusLabel(status)).trim();
        const progress = clampInt(data?.progress, 0, 100, status === 'borrador' ? 10 : 35);
        const total = toMoneyValue(data?.total);
        const updatedAt = formatNowForUi();
        const tipo = String(data?.tipo || 'emitidos').trim().toLowerCase();
        const dateFrom = String(data?.date_from || '').trim();
        const dateTo = String(data?.date_to || '').trim();
        const xmlCount = String(data?.xml_count || data?.xmlCount || '').trim();
        const discountCode = String(data?.discount_code_applied || data?.discount_code || '').trim();
        const ivaRate = String(data?.iva_rate || '16').trim();
        const notes = String(data?.notes || '').trim();

        const row = document.createElement('tr');
        row.id = quoteId !== '' ? `satQuoteRow-${quoteId}` : `satQuoteRow-${Date.now()}`;
        row.setAttribute('data-quote-row', 'true');
        row.setAttribute('data-quote-id', quoteId);
        row.setAttribute('data-status', status);
        row.setAttribute('data-search', `${folio} ${rfc} ${razonSocial} ${concepto} ${statusLabel}`);
        row.setAttribute('data-folio', folio);
        row.setAttribute('data-rfc', rfc);
        row.setAttribute('data-razon-social', razonSocial);
        row.setAttribute('data-concepto', concepto);
        row.setAttribute('data-progress', String(progress));
        row.setAttribute('data-total', String(total));
        row.setAttribute('data-date-from', dateFrom);
        row.setAttribute('data-date-to', dateTo);
        row.setAttribute('data-tipo', tipo);
        row.setAttribute('data-xml-count', xmlCount);
        row.setAttribute('data-discount-code', discountCode);
        row.setAttribute('data-iva-rate', ivaRate);
        row.setAttribute('data-notes', notes);

        row.innerHTML = `
            <td>
                <div class="sat-clean-quote-summary">
                    <span class="sat-clean-quote-folio" data-quote-field="folio">${escapeHtml(folio)}</span>
                </div>
            </td>

            <td>
                <div class="sat-clean-rfc-inline-main">
                    <span class="sat-clean-rfc-inline-main__rfc" data-quote-field="rfc">${escapeHtml(rfc || 'RFC pendiente')}</span>
                </div>
                <div class="sat-clean-rfc-inline-text" data-quote-field="razon_social">${escapeHtml(razonSocial || 'Razón social pendiente')}</div>
            </td>

            <td>
                <div class="sat-clean-rfc-inline-text" data-quote-field="concepto">${escapeHtml(concepto)}</div>
            </td>

            <td>
                <span class="sat-clean-status-badge ${resolveStatusBadgeClass(status)}" data-quote-field="status_badge">
                    ${escapeHtml(statusLabel)}
                </span>
            </td>

            <td>
                <span class="sat-clean-quote-amount" data-quote-field="importe_estimado">
                    ${formatMoney(total)}
                </span>
            </td>

            <td>
                <div class="sat-clean-quote-progress">
                    <div class="sat-clean-quote-progress__bar">
                        <span class="sat-clean-quote-progress__fill" data-quote-field="progress_fill" style="width: ${progress}%;"></span>
                    </div>
                    <span class="sat-clean-quote-progress__text" data-quote-field="progress_text">${progress}%</span>
                </div>
            </td>

            <td>
                <span class="sat-clean-quote-meta" data-quote-field="updated_at">${escapeHtml(updatedAt)}</span>
            </td>

            <td class="text-end">
                <div class="sat-clean-icon-actions">
                    <button
                        type="button"
                        class="sat-clean-icon-btn"
                        data-quote-action="view"
                        data-quote-id="${escapeHtml(quoteId)}"
                        title="Ver detalle"
                        aria-label="Ver detalle"
                    >
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M2.25 12C3.9 8.25 7.38 5.75 12 5.75C16.62 5.75 20.1 8.25 21.75 12C20.1 15.75 16.62 18.25 12 18.25C7.38 18.25 3.9 15.75 2.25 12Z" stroke="currentColor" stroke-width="1.8"/>
                            <circle cx="12" cy="12" r="3.25" stroke="currentColor" stroke-width="1.8"/>
                        </svg>
                    </button>

                    <button
                        type="button"
                        class="sat-clean-icon-btn"
                        data-quote-action="edit"
                        data-quote-id="${escapeHtml(quoteId)}"
                        title="Editar cotización"
                        aria-label="Editar cotización"
                    >
                        <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
                            <path d="M4 20H8L18.5 9.5C19.3284 8.67157 19.3284 7.32843 18.5 6.5V6.5C17.6716 5.67157 16.3284 5.67157 15.5 6.5L5 17V20Z" stroke="currentColor" stroke-width="1.8" stroke-linejoin="round"/>
                            <path d="M13.5 8.5L16.5 11.5" stroke="currentColor" stroke-width="1.8" stroke-linecap="round"/>
                        </svg>
                    </button>
                </div>
            </td>
        `;

        return row;
    }

    function updateQuoteRow(row, data) {
        const quoteId = String(data?.id || data?.draft_id || '').trim();
        const folio = String(data?.folio || row.getAttribute('data-folio') || '').trim();
        const rfc = String(data?.rfc || row.getAttribute('data-rfc') || '').trim().toUpperCase();
        const razonSocial = String(data?.razon_social || row.getAttribute('data-razon-social') || '').trim();
        const concepto = buildQuoteConceptoFromData(data);
        const status = normalizeText(String(data?.status || row.getAttribute('data-status') || 'borrador'));
        const statusLabel = String(data?.status_label || mapQuoteStatusLabel(status)).trim();
        const progress = clampInt(data?.progress, 0, 100, status === 'borrador' ? 10 : 35);
        const total = toMoneyValue(data?.total);
        const updatedAt = formatNowForUi();
        const tipo = String(data?.tipo || row.getAttribute('data-tipo') || 'emitidos').trim().toLowerCase();
        const dateFrom = String(data?.date_from || row.getAttribute('data-date-from') || '').trim();
        const dateTo = String(data?.date_to || row.getAttribute('data-date-to') || '').trim();
        const xmlCount = String(data?.xml_count || data?.xmlCount || row.getAttribute('data-xml-count') || '').trim();
        const discountCode = String(data?.discount_code_applied || data?.discount_code || row.getAttribute('data-discount-code') || '').trim();
        const ivaRate = String(data?.iva_rate || row.getAttribute('data-iva-rate') || '16').trim();
        const notes = String(data?.notes || row.getAttribute('data-notes') || '').trim();

        if (quoteId !== '') {
            row.id = `satQuoteRow-${quoteId}`;
            row.setAttribute('data-quote-id', quoteId);
        }

        row.setAttribute('data-status', status);
        row.setAttribute('data-search', `${folio} ${rfc} ${razonSocial} ${concepto} ${statusLabel}`);
        row.setAttribute('data-folio', folio);
        row.setAttribute('data-rfc', rfc);
        row.setAttribute('data-razon-social', razonSocial);
        row.setAttribute('data-concepto', concepto);
        row.setAttribute('data-progress', String(progress));
        row.setAttribute('data-total', String(total));
        row.setAttribute('data-date-from', dateFrom);
        row.setAttribute('data-date-to', dateTo);
        row.setAttribute('data-tipo', tipo);
        row.setAttribute('data-xml-count', xmlCount);
        row.setAttribute('data-discount-code', discountCode);
        row.setAttribute('data-iva-rate', ivaRate);
        row.setAttribute('data-notes', notes);

        const folioEl = row.querySelector('[data-quote-field="folio"]');
        const rfcEl = row.querySelector('[data-quote-field="rfc"]');
        const razonEl = row.querySelector('[data-quote-field="razon_social"]');
        const conceptoEl = row.querySelector('[data-quote-field="concepto"]');
        const badgeEl = row.querySelector('[data-quote-field="status_badge"]');
        const amountEl = row.querySelector('[data-quote-field="importe_estimado"]');
        const progressFillEl = row.querySelector('[data-quote-field="progress_fill"]');
        const progressTextEl = row.querySelector('[data-quote-field="progress_text"]');
        const updatedEl = row.querySelector('[data-quote-field="updated_at"]');
        const actionButtons = row.querySelectorAll('[data-quote-id]');

        if (folioEl) folioEl.textContent = folio;
        if (rfcEl) rfcEl.textContent = rfc || 'RFC pendiente';
        if (razonEl) razonEl.textContent = razonSocial || 'Razón social pendiente';
        if (conceptoEl) conceptoEl.textContent = concepto;

        if (badgeEl) {
            badgeEl.className = `sat-clean-status-badge ${resolveStatusBadgeClass(status)}`;
            badgeEl.textContent = statusLabel;
        }

        if (amountEl) amountEl.textContent = formatMoney(total);

        if (progressFillEl) {
            progressFillEl.style.width = `${progress}%`;
        }

        if (progressTextEl) {
            progressTextEl.textContent = `${progress}%`;
        }

        if (updatedEl) {
            updatedEl.textContent = updatedAt;
        }

        actionButtons.forEach((btn) => {
            btn.setAttribute('data-quote-id', quoteId);
        });
    }

    function populateQuoteDetailModal(row) {
        const quoteId = String(row.getAttribute('data-quote-id') || '').trim();
        const folio = String(row.getAttribute('data-folio') || '').trim();
        const rfc = String(row.getAttribute('data-rfc') || '').trim();
        const razonSocial = decodeHtmlEntities(String(row.getAttribute('data-razon-social') || '').trim());
        const concepto = decodeHtmlEntities(String(row.getAttribute('data-concepto') || '').trim());
        const status = String(row.getAttribute('data-status') || '').trim();
        const progress = String(row.getAttribute('data-progress') || '').trim();
        const total = String(row.getAttribute('data-total') || '').trim();
        const dateFrom = String(row.getAttribute('data-date-from') || '').trim();
        const dateTo = String(row.getAttribute('data-date-to') || '').trim();
        const tipo = String(row.getAttribute('data-tipo') || '').trim();

        setInputValue('satQuoteDetailFolio', folio);
        setInputValue('satQuoteDetailRfc', rfc);
        setInputValue('satQuoteDetailStatus', mapQuoteStatusLabel(status));
        setInputValue('satQuoteDetailRazonSocial', razonSocial || 'Sin razón social');
        setInputValue('satQuoteDetailTipo', formatTipoSolicitudLabel(tipo || 'emitidos'));
        setInputValue('satQuoteDetailDateFrom', dateFrom !== '' ? formatShortDate(normalizeDateForInput(dateFrom) || dateFrom) : 'Sin fecha');
        setInputValue('satQuoteDetailDateTo', dateTo !== '' ? formatShortDate(normalizeDateForInput(dateTo) || dateTo) : 'Sin fecha');
        setInputValue('satQuoteDetailProgress', `${progress || '0'}%`);
        setInputValue('satQuoteDetailTotal', total !== '' ? formatMoney(total) : 'Pendiente');
        setTextareaValue('satQuoteDetailConcepto', concepto || 'Sin concepto');

        const detailEditBtn = document.getElementById('satQuoteDetailEditBtn');
        if (detailEditBtn) {
            detailEditBtn.setAttribute('data-quote-id', quoteId);
        }
    }

    function populateQuoteEditModal(row) {
        const quoteId = String(row.getAttribute('data-quote-id') || '').trim();
        const folio = String(row.getAttribute('data-folio') || '').trim();
        const rfc = String(row.getAttribute('data-rfc') || '').trim();
        const concepto = decodeHtmlEntities(String(row.getAttribute('data-concepto') || '').trim());
        const status = String(row.getAttribute('data-status') || '').trim();
        const progress = String(row.getAttribute('data-progress') || '').trim();
        const total = String(row.getAttribute('data-total') || '').trim();
        const dateFrom = String(row.getAttribute('data-date-from') || '').trim();
        const dateTo = String(row.getAttribute('data-date-to') || '').trim();
        const tipo = String(row.getAttribute('data-tipo') || 'emitidos').trim().toLowerCase();

        setInputValue('satQuoteEditId', quoteId);
        setInputValue('satQuoteEditDraftId', quoteId);
        setInputValue('satQuoteEditFolio', folio);
        setInputValue('satQuoteEditRfc', rfc);
        setSelectValue('satQuoteEditTipo', ['emitidos', 'recibidos', 'ambos'].includes(tipo) ? tipo : 'emitidos');
        setInputValue('satQuoteEditDateFrom', normalizeDateForInput(dateFrom));
        setInputValue('satQuoteEditDateTo', normalizeDateForInput(dateTo));
        setInputValue('satQuoteEditTotal', total !== '' ? formatMoney(total) : 'Pendiente');
        setInputValue('satQuoteEditProgress', `${progress || '0'}%`);
        setInputValue('satQuoteEditStatus', mapQuoteStatusLabel(status));
        setTextareaValue('satQuoteEditConcepto', concepto || '');
    }

    function buildEditModalPayload() {
        const draftId = String(document.getElementById('satQuoteEditDraftId')?.value || '').trim();
        const folio = String(document.getElementById('satQuoteEditFolio')?.value || '').trim();
        const rfc = String(document.getElementById('satQuoteEditRfc')?.value || '').trim().toUpperCase();
        const tipo = String(document.getElementById('satQuoteEditTipo')?.value || 'emitidos').trim().toLowerCase();
        const dateFrom = String(document.getElementById('satQuoteEditDateFrom')?.value || '').trim();
        const dateTo = String(document.getElementById('satQuoteEditDateTo')?.value || '').trim();
        const concepto = String(document.getElementById('satQuoteEditConcepto')?.value || '').trim();

        if (rfc === '') {
            return { ok: false, message: 'No se encontró el RFC de la cotización.' };
        }

        if (dateFrom === '' || dateTo === '') {
            return { ok: false, message: 'Debes definir fecha inicial y final en la edición.' };
        }

        if (dateFrom > dateTo) {
            return { ok: false, message: 'La fecha inicial no puede ser mayor a la final.' };
        }

        const row = draftId !== ''
            ? document.querySelector(`[data-quote-row="true"][data-quote-id="${cssAttributeEscape(draftId)}"]`)
            : null;

        const rfcId = row
            ? String(row.getAttribute('data-rfc-id') || '').trim()
            : '';

        const razonSocial = row
            ? decodeHtmlEntities(String(row.getAttribute('data-razon-social') || '').trim())
            : '';

        const detectedXml = row
            ? extractNumberFromText(row.querySelector('[data-quote-field="concepto"]')?.textContent || concepto)
            : extractNumberFromText(concepto);

        return {
            ok: true,
            data: {
                draft_id: draftId,
                folio,
                rfc_id: rfcId,
                rfc,
                razon_social: razonSocial,
                tipo,
                date_from: dateFrom,
                date_to: dateTo,
                xml_count: detectedXml > 0 ? detectedXml : '',
                concepto,
            },
        };
    }

    function applyPayloadToMainQuoteModal(data) {
        const draftIdInput = document.getElementById('satQuoteDraftId');
        const selectedRfcIdInput = document.getElementById('satQuoteSelectedRfcId');
        const selectedRfcInput = document.getElementById('satQuoteSelectedRfc');
        const selectedRazonSocialInput = document.getElementById('satQuoteSelectedRazonSocial');
        const selectedBadge = document.getElementById('satQuoteSelectedBadge');
        const tipoInput = document.getElementById('satQuoteTipoSolicitud');
        const dateFromInput = document.getElementById('satQuoteDateFrom');
        const dateToInput = document.getElementById('satQuoteDateTo');
        const xmlCountInput = document.getElementById('satQuoteXmlCount');
        const notesInput = document.getElementById('satQuoteNotes');

        if (draftIdInput) draftIdInput.value = String(data.draft_id || '').trim();
        if (selectedRfcIdInput) selectedRfcIdInput.value = String(data.rfc_id || '').trim();
        if (selectedRfcInput) selectedRfcInput.value = String(data.rfc || '').trim().toUpperCase();
        if (selectedRazonSocialInput) selectedRazonSocialInput.value = String(data.razon_social || '').trim();

        if (selectedBadge) {
            const rfc = String(data.rfc || '').trim().toUpperCase();
            const razon = String(data.razon_social || '').trim();
            selectedBadge.textContent = razon !== '' ? `${rfc} · ${razon}` : rfc;
        }

        if (tipoInput) {
            tipoInput.value = ['emitidos', 'recibidos', 'ambos'].includes(String(data.tipo || '').trim())
                ? String(data.tipo || '').trim()
                : 'emitidos';
        }

        if (dateFromInput) dateFromInput.value = String(data.date_from || '').trim();
        if (dateToInput) dateToInput.value = String(data.date_to || '').trim();
        if (xmlCountInput) xmlCountInput.value = data.xml_count !== '' ? String(data.xml_count) : '';

        if (notesInput && String(data.concepto || '').trim() !== '') {
            notesInput.value = String(data.concepto || '').trim();
        }

        renderQuoteRfcOptions('');
        updateQuoteSummaryPreview();
    }

    function buildQuoteConceptoFromData(data) {
        const explicitConcept = String(data?.concepto || '').trim();
        if (explicitConcept !== '') {
            return explicitConcept;
        }

        const tipo = String(data?.tipo || 'emitidos').trim().toLowerCase();
        const dateFrom = String(data?.date_from || '').trim();
        const dateTo = String(data?.date_to || '').trim();

        let label = 'Cotización SAT emitidos';
        if (tipo === 'recibidos') label = 'Cotización SAT recibidos';
        if (tipo === 'ambos') label = 'Cotización SAT ambos';

        if (dateFrom !== '' && dateTo !== '') {
            return `${label} · ${formatShortDate(dateFrom)} al ${formatShortDate(dateTo)}`;
        }

        return label;
    }

    function mapQuoteStatusLabel(status) {
        switch (normalizeText(status)) {
            case 'borrador':
                return 'Borrador';
            case 'cotizada':
                return 'Cotizada';
            case 'pagada':
                return 'Pagada';
            case 'completada':
                return 'Completada';
            case 'cancelada':
                return 'Cancelada';
            case 'en_proceso':
            default:
                return 'En proceso de cotización';
        }
    }

    function resolveStatusBadgeClass(status) {
        const normalized = normalizeText(status);

        if (normalized === 'pagada' || normalized === 'completada') {
            return 'is-success';
        }

        if (
            normalized === 'en_proceso' ||
            normalized === 'cotizada' ||
            normalized === 'pendiente_pago' ||
            normalized === 'en_descarga'
        ) {
            return 'is-warning';
        }

        return 'is-muted';
    }

    function formatNowForUi() {
        const now = new Date();
        const dd = String(now.getDate()).padStart(2, '0');
        const mm = String(now.getMonth() + 1).padStart(2, '0');
        const yyyy = String(now.getFullYear());
        const hh = String(now.getHours()).padStart(2, '0');
        const ii = String(now.getMinutes()).padStart(2, '0');

        return `${dd}/${mm}/${yyyy} ${hh}:${ii}`;
    }

    function clampInt(value, min, max, fallback) {
        const parsed = parseInt(value, 10);
        if (!Number.isFinite(parsed)) {
            return fallback;
        }

        return Math.max(min, Math.min(max, parsed));
    }

    function normalizeDateForInput(value) {
        const raw = String(value || '').trim();
        if (raw === '') return '';

        if (/^\d{4}-\d{2}-\d{2}$/.test(raw)) {
            return raw;
        }

        const parts = raw.split('/');
        if (parts.length === 3) {
            const dd = parts[0].padStart(2, '0');
            const mm = parts[1].padStart(2, '0');
            const yyyy = parts[2];
            if (/^\d{4}$/.test(yyyy)) {
                return `${yyyy}-${mm}-${dd}`;
            }
        }

        return '';
    }

    function extractNumberFromText(value) {
        const match = String(value || '').match(/\d+/);
        return match ? parseInt(match[0], 10) : 0;
    }

    function cssEscapeSimple(value) {
        return String(value || '').replace(/[^a-zA-Z0-9\-_]/g, '\\$&');
    }

    function cssAttributeEscape(value) {
        return String(value || '').replace(/\\/g, '\\\\').replace(/"/g, '\\"');
    }

    function setInputValue(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.value = value ?? '';
        }
    }

    function setTextareaValue(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.value = value ?? '';
        }
    }

    function setSelectValue(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.value = value ?? '';
        }
    }

    function openModal(modal) {
        modal.classList.add('is-visible');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sat-clean-modal-open');
    }

    function closeModal(modal) {
        modal.classList.remove('is-visible');
        modal.setAttribute('aria-hidden', 'true');

        const hasVisibleModal = document.querySelector('.sat-clean-modal.is-visible');
        const passwordDialogVisible = document.getElementById('satPasswordDialog')?.classList.contains('is-visible');

        if (!hasVisibleModal && !passwordDialogVisible) {
            document.body.classList.remove('sat-clean-modal-open');
        }
    }

    function extractRfcFromRow(row) {
        if (!row) {
            return 'RFC';
        }

        const rfcCell =
            row.querySelector('.sat-clean-rfc-inline-main__rfc') ||
            row.querySelector('.sat-clean-rfc-cell-main__title');

        const rfcText = rfcCell ? rfcCell.textContent.trim() : '';
        return rfcText !== '' ? rfcText : 'RFC';
    }

    function updateVisibleCounter(rows) {
        const counter = document.querySelector('.sat-clean-rfc-toolbar-v2__count');
        if (!counter) {
            return;
        }

        const visibleRows = Array.from(rows).filter((row) => row.style.display !== 'none');
        counter.textContent = `${visibleRows.length} registro(s)`;
    }

    function ensurePasswordDialog() {
        if (document.getElementById('satPasswordDialog')) {
            bindPasswordDialogEvents();
            return;
        }

        const dialog = document.createElement('div');
        dialog.id = 'satPasswordDialog';
        dialog.className = 'sat-clean-password-dialog';
        dialog.setAttribute('aria-hidden', 'true');

        dialog.innerHTML = `
            <div class="sat-clean-password-dialog__backdrop" data-password-dialog-close></div>
            <div class="sat-clean-password-dialog__box" role="dialog" aria-modal="true" aria-labelledby="satPasswordDialogTitle">
                <div class="sat-clean-password-dialog__header">
                    <div>
                        <h3 class="sat-clean-password-dialog__title" id="satPasswordDialogTitle">Contraseña SAT</h3>
                        <p class="sat-clean-password-dialog__subtitle" id="satPasswordDialogSubtitle">Acceso protegido</p>
                    </div>
                    <button type="button" class="sat-clean-password-dialog__close" data-password-dialog-close aria-label="Cerrar">✕</button>
                </div>

                <div class="sat-clean-password-dialog__body">
                    <div class="sat-clean-password-dialog__meta" id="satPasswordDialogMeta">RFC</div>

                    <div class="sat-clean-password-dialog__field">
                        <input
                            type="password"
                            id="satPasswordDialogInput"
                            class="sat-clean-password-dialog__input"
                            value=""
                            readonly
                        >
                        <button
                            type="button"
                            class="sat-clean-password-dialog__toggle"
                            id="satPasswordDialogToggle"
                            aria-label="Ver u ocultar contraseña"
                            title="Ver u ocultar contraseña"
                        >
                            Ver
                        </button>
                    </div>

                    <div class="sat-clean-password-dialog__helper" id="satPasswordDialogHelper">
                        Contraseña protegida
                    </div>
                </div>

                <div class="sat-clean-password-dialog__actions">
                    <button type="button" class="sat-clean-btn sat-clean-btn--ghost sat-clean-btn--compact" id="satPasswordDialogCopy">
                        Copiar
                    </button>
                    <button type="button" class="sat-clean-btn sat-clean-btn--primary sat-clean-btn--compact" data-password-dialog-close>
                        Cerrar
                    </button>
                </div>
            </div>
        `;

        document.body.appendChild(dialog);
        injectPasswordDialogStyles();
        bindPasswordDialogEvents();
    }

    function bindPasswordDialogEvents() {
        const dialog = document.getElementById('satPasswordDialog');
        if (!dialog) {
            return;
        }

        const closeButtons = dialog.querySelectorAll('[data-password-dialog-close]');
        const toggleButton = document.getElementById('satPasswordDialogToggle');
        const copyButton = document.getElementById('satPasswordDialogCopy');
        const input = document.getElementById('satPasswordDialogInput');

        closeButtons.forEach((button) => {
            button.addEventListener('click', hidePasswordDialog);
        });

        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                hidePasswordDialog();
            }
        });

        const backdrop = dialog.querySelector('.sat-clean-password-dialog__backdrop');
        if (backdrop) {
            backdrop.addEventListener('click', hidePasswordDialog);
        }

        if (toggleButton && input) {
            toggleButton.addEventListener('click', () => {
                const isHidden = input.getAttribute('type') === 'password';
                input.setAttribute('type', isHidden ? 'text' : 'password');
                toggleButton.textContent = isHidden ? 'Ocultar' : 'Ver';
            });
        }

        if (copyButton && input) {
            copyButton.addEventListener('click', async () => {
                const password = String(input.value || '').trim();

                if (password === '') {
                    showPortalNotice('No hay contraseña para copiar.', 'warning');
                    return;
                }

                const copied = await copyToClipboard(password);

                if (copied) {
                    showPortalNotice('Contraseña copiada al portapapeles.', 'success');
                } else {
                    showPortalNotice('No se pudo copiar automáticamente.', 'warning');
                }
            });
        }
    }

    async function showPasswordDialog({ label, rfc, password }) {
        const dialog = document.getElementById('satPasswordDialog');
        const meta = document.getElementById('satPasswordDialogMeta');
        const subtitle = document.getElementById('satPasswordDialogSubtitle');
        const input = document.getElementById('satPasswordDialogInput');
        const helper = document.getElementById('satPasswordDialogHelper');
        const toggleButton = document.getElementById('satPasswordDialogToggle');

        if (!dialog || !meta || !subtitle || !input || !helper || !toggleButton) {
            const copied = await copyToClipboard(password);
            window.alert(`${label} · ${rfc}\n\n${password}\n\n${copied ? 'La contraseña se copió al portapapeles.' : 'No se pudo copiar automáticamente.'}`);
            return;
        }

        subtitle.textContent = `Contraseña ${label}`;
        meta.textContent = rfc;
        input.value = password;
        input.setAttribute('type', 'password');
        helper.textContent = 'Pulsa "Ver" para mostrarla o "Copiar" para copiarla.';
        toggleButton.textContent = 'Ver';

        dialog.classList.add('is-visible');
        dialog.setAttribute('aria-hidden', 'false');
        document.body.classList.add('sat-clean-modal-open');

        window.requestAnimationFrame(() => {
            input.focus();
            input.select();
        });
    }

    function hidePasswordDialog() {
        const dialog = document.getElementById('satPasswordDialog');
        if (!dialog) {
            return;
        }

        dialog.classList.remove('is-visible');
        dialog.setAttribute('aria-hidden', 'true');

        const hasVisibleModal = document.querySelector('.sat-clean-modal.is-visible');
        if (!hasVisibleModal) {
            document.body.classList.remove('sat-clean-modal-open');
        }
    }

    async function copyToClipboard(text) {
        const value = String(text || '');
        if (value === '') {
            return false;
        }

        try {
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                await navigator.clipboard.writeText(value);
                return true;
            }
        } catch (error) {
            console.warn('[SAT Portal] Clipboard API no disponible:', error);
        }

        try {
            const textarea = document.createElement('textarea');
            textarea.value = value;
            textarea.setAttribute('readonly', 'readonly');
            textarea.style.position = 'fixed';
            textarea.style.opacity = '0';
            textarea.style.pointerEvents = 'none';
            textarea.style.left = '-9999px';

            document.body.appendChild(textarea);
            textarea.focus();
            textarea.select();

            const ok = document.execCommand('copy');
            document.body.removeChild(textarea);

            return ok;
        } catch (error) {
            console.warn('[SAT Portal] execCommand copy falló:', error);
            return false;
        }
    }

    function ensureActionTooltips() {
        const buttons = document.querySelectorAll('.sat-clean-icon-btn, .sat-clean-password-pill__action');

        buttons.forEach((button) => {
            const currentTitle = String(button.getAttribute('title') || '').trim();
            const currentAria = String(button.getAttribute('aria-label') || '').trim();

            if (currentTitle === '' && currentAria !== '') {
                button.setAttribute('title', currentAria);
            }

            if (currentAria === '' && currentTitle !== '') {
                button.setAttribute('aria-label', currentTitle);
            }
        });
    }

    function initKeyboardShortcuts() {
        document.addEventListener('keydown', (event) => {
            if ((event.ctrlKey || event.metaKey) && event.key.toLowerCase() === 'n') {
                const activeQuoteModal = document.getElementById('satQuoteModal');
                if (activeQuoteModal && activeQuoteModal.classList.contains('is-visible')) {
                    return;
                }

                const createModalButton = document.querySelector('[data-rfc-open-modal="create"]');
                if (createModalButton) {
                    event.preventDefault();
                    createModalButton.click();
                }
            }

            if ((event.ctrlKey || event.metaKey) && event.shiftKey && event.key.toLowerCase() === 'q') {
                event.preventDefault();
                resetQuoteModalState();
                openQuoteModal();
            }
        });
    }

        const SAT_QUOTE_DRAFT_STORAGE_KEY = 'p360_sat_quote_pending_draft_v1';

    function persistCurrentQuoteDraftToStorage(extra = {}) {
        try {
            const payload = {
                draft_id: String(document.getElementById('satQuoteDraftId')?.value || '').trim(),
                rfc_id: String(document.getElementById('satQuoteSelectedRfcId')?.value || '').trim(),
                rfc: String(document.getElementById('satQuoteSelectedRfc')?.value || '').trim().toUpperCase(),
                razon_social: String(document.getElementById('satQuoteSelectedRazonSocial')?.value || '').trim(),
                tipo: String(document.getElementById('satQuoteTipoSolicitud')?.value || 'emitidos').trim(),
                date_from: String(document.getElementById('satQuoteDateFrom')?.value || '').trim(),
                date_to: String(document.getElementById('satQuoteDateTo')?.value || '').trim(),
                xml_count: String(document.getElementById('satQuoteXmlCount')?.value || '').trim(),
                discount_code: String(document.getElementById('satQuoteDiscountCode')?.value || '').trim(),
                iva_rate: String(document.getElementById('satQuoteIvaRate')?.value || '16').trim(),
                notes: String(document.getElementById('satQuoteNotes')?.value || '').trim(),
                reopen_after_rfc: extra.reopen_after_rfc === true,
                saved_at: new Date().toISOString(),
            };

            window.sessionStorage.setItem(
                SAT_QUOTE_DRAFT_STORAGE_KEY,
                JSON.stringify(payload)
            );
        } catch (error) {
            console.warn('[SAT Portal] No se pudo persistir el borrador temporal:', error);
        }
    }

    function clearPendingQuoteDraftStorage() {
        try {
            window.sessionStorage.removeItem(SAT_QUOTE_DRAFT_STORAGE_KEY);
        } catch (error) {
            console.warn('[SAT Portal] No se pudo limpiar el borrador temporal:', error);
        }
    }

    function restorePendingQuoteDraftFromStorage() {
        try {
            const raw = window.sessionStorage.getItem(SAT_QUOTE_DRAFT_STORAGE_KEY);
            if (!raw) {
                return;
            }

            const draft = JSON.parse(raw);
            if (!draft || typeof draft !== 'object') {
                clearPendingQuoteDraftStorage();
                return;
            }

            const draftIdInput = document.getElementById('satQuoteDraftId');
            const selectedRfcIdInput = document.getElementById('satQuoteSelectedRfcId');
            const selectedRfcInput = document.getElementById('satQuoteSelectedRfc');
            const selectedRazonSocialInput = document.getElementById('satQuoteSelectedRazonSocial');
            const selectedBadge = document.getElementById('satQuoteSelectedBadge');
            const tipoInput = document.getElementById('satQuoteTipoSolicitud');
            const dateFromInput = document.getElementById('satQuoteDateFrom');
            const dateToInput = document.getElementById('satQuoteDateTo');
            const xmlCountInput = document.getElementById('satQuoteXmlCount');
            const discountInput = document.getElementById('satQuoteDiscountCode');
            const ivaInput = document.getElementById('satQuoteIvaRate');
            const notesInput = document.getElementById('satQuoteNotes');

            if (draftIdInput) draftIdInput.value = String(draft.draft_id || '').trim();
            if (selectedRfcIdInput) selectedRfcIdInput.value = String(draft.rfc_id || '').trim();
            if (selectedRfcInput) selectedRfcInput.value = String(draft.rfc || '').trim().toUpperCase();
            if (selectedRazonSocialInput) selectedRazonSocialInput.value = String(draft.razon_social || '').trim();
            if (tipoInput) tipoInput.value = ['emitidos', 'recibidos', 'ambos'].includes(String(draft.tipo || '').trim()) ? String(draft.tipo).trim() : 'emitidos';
            if (dateFromInput) dateFromInput.value = String(draft.date_from || '').trim();
            if (dateToInput) dateToInput.value = String(draft.date_to || '').trim();
            if (xmlCountInput) xmlCountInput.value = String(draft.xml_count || '').trim();
            if (discountInput) discountInput.value = String(draft.discount_code || '').trim();
            if (ivaInput) ivaInput.value = String(draft.iva_rate || '16').trim();
            if (notesInput) notesInput.value = String(draft.notes || '').trim();

            if (selectedBadge) {
                const rfc = String(draft.rfc || '').trim().toUpperCase();
                const razon = String(draft.razon_social || '').trim();
                selectedBadge.textContent = rfc !== ''
                    ? (razon !== '' ? `${rfc} · ${razon}` : rfc)
                    : 'Ningún RFC seleccionado';
            }

            renderQuoteRfcOptions(String(document.getElementById('satQuoteRfcSearch')?.value || ''));
            updateQuoteSummaryPreview();

            if (draft.reopen_after_rfc === true && String(draft.rfc || '').trim() !== '') {
                window.setTimeout(() => {
                    openQuoteModal();
                    showPortalNotice('Se recuperó la cotización pendiente.', 'success');
                }, 120);
            }
        } catch (error) {
            console.warn('[SAT Portal] No se pudo restaurar el borrador temporal:', error);
            clearPendingQuoteDraftStorage();
        }
    }

    function decodeHtmlEntities(value) {
        const textarea = document.createElement('textarea');
        textarea.innerHTML = String(value || '');
        return textarea.value;
    }

    function normalizeText(value) {
        return String(value || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/\s+/g, ' ')
            .trim()
            .toLowerCase();
    }

    function showPortalNotice(message, type = 'info') {
        let container = document.querySelector('.sat-clean-toast-stack');

        if (!container) {
            container = document.createElement('div');
            container.className = 'sat-clean-toast-stack';
            document.body.appendChild(container);
        }

        const toast = document.createElement('div');
        toast.className = `sat-clean-toast sat-clean-toast--${type}`;
        toast.textContent = message;

        container.appendChild(toast);

        window.requestAnimationFrame(() => {
            toast.classList.add('is-visible');
        });

        window.setTimeout(() => {
            toast.classList.remove('is-visible');

            window.setTimeout(() => {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }

                if (container && container.childElementCount === 0 && container.parentNode) {
                    container.parentNode.removeChild(container);
                }
            }, 220);
        }, 2600);
    }

    function buildAjaxHeaders() {
        const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || '';

        return {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(token !== '' ? { 'X-CSRF-TOKEN': token } : {}),
        };
    }

    function toMoneyValue(value) {
        const amount = parseFloat(value);
        return Number.isFinite(amount) ? amount : 0;
    }

    function formatMoney(value) {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2,
        }).format(toMoneyValue(value));
    }

    function numberFormat(value) {
        const amount = parseInt(value || 0, 10) || 0;
        return new Intl.NumberFormat('es-MX').format(amount);
    }

    function formatShortDate(value) {
        if (!value) return 'Sin definir';

        const parts = String(value).split('-');
        if (parts.length === 3) {
            return `${parts[2]}/${parts[1]}/${parts[0]}`;
        }

        if (value.includes('/')) {
            return value;
        }

        return value;
    }

    function formatTipoSolicitudLabel(tipo) {
        switch (String(tipo || '').trim()) {
            case 'recibidos':
                return 'Recibidos';
            case 'ambos':
                return 'Ambos';
            case 'emitidos':
            default:
                return 'Emitidos';
        }
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function injectPasswordDialogStyles() {
        if (document.getElementById('satPasswordDialogStyles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'satPasswordDialogStyles';
        style.textContent = `
            .sat-clean-password-dialog{
                position:fixed;
                inset:0;
                z-index:10030;
                display:none;
                align-items:center;
                justify-content:center;
                padding:24px;
            }

            .sat-clean-password-dialog.is-visible{
                display:flex;
            }

            .sat-clean-password-dialog__backdrop{
                position:absolute;
                inset:0;
                background:rgba(15,34,64,.42);
                backdrop-filter:blur(3px);
            }

            .sat-clean-password-dialog__box{
                position:relative;
                z-index:1;
                width:min(100%, 460px);
                background:#ffffff;
                border:1px solid #dfe8f4;
                border-radius:20px;
                box-shadow:0 26px 60px rgba(15,34,64,.22);
                overflow:hidden;
            }

            .sat-clean-password-dialog__header{
                display:flex;
                align-items:flex-start;
                justify-content:space-between;
                gap:16px;
                padding:18px 18px 14px 18px;
                border-bottom:1px solid #edf2f8;
                background:linear-gradient(180deg, #ffffff 0%, #fbfdff 100%);
            }

            .sat-clean-password-dialog__title{
                margin:0 0 4px 0;
                color:#132238;
                font-size:18px;
                line-height:1.2;
                font-weight:800;
                letter-spacing:-.02em;
            }

            .sat-clean-password-dialog__subtitle{
                margin:0;
                color:#5f7187;
                font-size:12px;
                line-height:1.5;
                font-weight:500;
            }

            .sat-clean-password-dialog__close{
                appearance:none;
                border:none;
                outline:none;
                cursor:pointer;
                width:34px;
                height:34px;
                border-radius:10px;
                display:inline-flex;
                align-items:center;
                justify-content:center;
                background:#f3f6fb;
                border:1px solid #e1e8f3;
                color:#27488d;
                font-size:14px;
                font-weight:800;
                line-height:1;
            }

            .sat-clean-password-dialog__body{
                padding:18px;
                display:flex;
                flex-direction:column;
                gap:12px;
            }

            .sat-clean-password-dialog__meta{
                display:inline-flex;
                align-items:center;
                width:max-content;
                min-height:28px;
                max-width:100%;
                padding:0 10px;
                border-radius:999px;
                background:#f4f7fc;
                border:1px solid #e3ebf5;
                color:#60738b;
                font-size:11px;
                font-weight:700;
                line-height:1;
            }

            .sat-clean-password-dialog__field{
                display:flex;
                align-items:center;
                gap:10px;
            }

            .sat-clean-password-dialog__input{
                flex:1 1 auto;
                min-width:0;
                height:42px;
                border:1px solid #dfe8f4;
                border-radius:12px;
                background:#ffffff;
                color:#132238;
                padding:0 14px;
                font-size:13px;
                font-weight:700;
                outline:none;
            }

            .sat-clean-password-dialog__toggle{
                appearance:none;
                border:none;
                outline:none;
                cursor:pointer;
                height:42px;
                padding:0 14px;
                border-radius:12px;
                background:#edf4ff;
                border:1px solid #dbe8ff;
                color:#234b92;
                font-size:12px;
                font-weight:800;
                white-space:nowrap;
            }

            .sat-clean-password-dialog__helper{
                color:#5f7187;
                font-size:12px;
                line-height:1.55;
                font-weight:500;
            }

            .sat-clean-password-dialog__actions{
                display:flex;
                align-items:center;
                justify-content:flex-end;
                gap:10px;
                padding:0 18px 18px 18px;
            }

            @media (max-width: 767px){
                .sat-clean-password-dialog{
                    padding:14px;
                }

                .sat-clean-password-dialog__box{
                    width:100%;
                    border-radius:18px;
                }

                .sat-clean-password-dialog__header{
                    padding:16px 16px 12px 16px;
                }

                .sat-clean-password-dialog__body{
                    padding:16px;
                }

                .sat-clean-password-dialog__field{
                    flex-direction:column;
                    align-items:stretch;
                }

                .sat-clean-password-dialog__actions{
                    padding:0 16px 16px 16px;
                    flex-direction:column;
                }

                .sat-clean-password-dialog__actions .sat-clean-btn{
                    width:100%;
                }
            }
        `;

        document.head.appendChild(style);
    }

    function injectQuoteModalStyles() {
        if (document.getElementById('satQuoteModalStyles')) {
            return;
        }

        const style = document.createElement('style');
        style.id = 'satQuoteModalStyles';
        style.textContent = `
            .sat-clean-quote-modal__dialog{
                width:min(1100px, calc(100vw - 32px));
            }

            .sat-clean-quote-rfc-picker{
                display:flex;
                flex-direction:column;
                gap:14px;
            }

            .sat-clean-quote-rfc-picker__top{
                display:grid;
                grid-template-columns:minmax(0, 1fr) auto;
                gap:12px;
                align-items:end;
            }

            .sat-clean-quote-rfc-picker__actions{
                display:flex;
                align-items:center;
                gap:10px;
            }

            .sat-clean-quote-rfc-picker__selected{
                min-height:44px;
                display:flex;
                align-items:center;
                padding:0 14px;
                border:1px solid #dbe6f4;
                border-radius:14px;
                background:#f8fbff;
                color:#24406d;
                font-size:13px;
                font-weight:700;
            }

            .sat-clean-quote-rfc-picker__list{
                display:grid;
                grid-template-columns:repeat(2, minmax(0, 1fr));
                gap:10px;
                max-height:280px;
                overflow:auto;
                padding-right:2px;
            }

            .sat-clean-quote-rfc-picker__empty{
                border:1px dashed #dbe6f4;
                border-radius:14px;
                background:#fbfdff;
                padding:18px;
                text-align:center;
                color:#6a7895;
                font-size:13px;
                font-weight:600;
            }

            .sat-clean-quote-rfc-option{
                appearance:none;
                border:none;
                outline:none;
                cursor:pointer;
                width:100%;
                text-align:left;
                padding:14px;
                border-radius:14px;
                background:#ffffff;
                border:1px solid #dbe6f4;
                display:flex;
                flex-direction:column;
                gap:6px;
                transition:.18s ease;
            }

            .sat-clean-quote-rfc-option:hover{
                border-color:#8fb2ff;
                box-shadow:0 8px 24px rgba(34, 74, 150, .08);
                transform:translateY(-1px);
            }

            .sat-clean-quote-rfc-option.is-selected{
                border-color:#5f8fff;
                background:linear-gradient(180deg, #f8fbff 0%, #eef4ff 100%);
                box-shadow:0 10px 28px rgba(34, 74, 150, .12);
            }

            .sat-clean-quote-rfc-option__main{
                color:#16356d;
                font-size:13px;
                font-weight:800;
                line-height:1.2;
            }

            .sat-clean-quote-rfc-option__sub{
                color:#60708b;
                font-size:12px;
                font-weight:600;
                line-height:1.45;
            }

            .sat-clean-quote-resume{
                display:flex;
                flex-direction:column;
                gap:14px;
            }

            .sat-clean-quote-resume__grid{
                display:grid;
                grid-template-columns:repeat(4, minmax(0, 1fr));
                gap:12px;
            }

            .sat-clean-quote-resume__item{
                padding:14px;
                border-radius:16px;
                background:#ffffff;
                border:1px solid #dde7f4;
                min-height:76px;
                display:flex;
                flex-direction:column;
                justify-content:center;
                gap:6px;
            }

            .sat-clean-quote-resume__label{
                color:#70809d;
                font-size:11px;
                font-weight:700;
                text-transform:uppercase;
                letter-spacing:.04em;
            }

            .sat-clean-quote-resume__value{
                color:#14284f;
                font-size:14px;
                font-weight:800;
                line-height:1.35;
            }

            .sat-clean-quote-result{
                min-height:140px;
                border:1px dashed #d6e2f1;
                border-radius:18px;
                background:#fbfdff;
                padding:16px;
            }

            .sat-clean-quote-result__placeholder,
            .sat-clean-quote-result__loading{
                min-height:108px;
                display:flex;
                align-items:center;
                justify-content:center;
                text-align:center;
                color:#6e7d99;
                font-size:13px;
                font-weight:600;
            }

            .sat-clean-quote-result__card{
                display:flex;
                flex-direction:column;
                gap:14px;
            }

            .sat-clean-quote-result__head{
                display:flex;
                align-items:flex-start;
                justify-content:space-between;
                gap:12px;
            }

            .sat-clean-quote-result__mode{
                color:#6b7a97;
                font-size:11px;
                font-weight:800;
                text-transform:uppercase;
                letter-spacing:.05em;
                margin-bottom:4px;
            }

            .sat-clean-quote-result__folio{
                color:#17306b;
                font-size:18px;
                font-weight:900;
                letter-spacing:-.02em;
            }

            .sat-clean-quote-result__watermark{
                display:inline-flex;
                align-items:center;
                justify-content:center;
                min-height:30px;
                padding:0 12px;
                border-radius:999px;
                background:rgba(255, 158, 48, .12);
                border:1px solid rgba(255, 158, 48, .28);
                color:#d46f00;
                font-size:11px;
                font-weight:900;
                letter-spacing:.06em;
                white-space:nowrap;
            }

            .sat-clean-quote-result__grid{
                display:grid;
                grid-template-columns:repeat(3, minmax(0, 1fr));
                gap:12px;
            }

            .sat-clean-quote-result__item{
                padding:13px 14px;
                border-radius:14px;
                background:#ffffff;
                border:1px solid #dfe8f4;
                display:flex;
                flex-direction:column;
                gap:6px;
            }

            .sat-clean-quote-result__item span{
                color:#6d7c97;
                font-size:11px;
                font-weight:700;
                text-transform:uppercase;
                letter-spacing:.04em;
            }

            .sat-clean-quote-result__item strong{
                color:#14284f;
                font-size:15px;
                font-weight:900;
            }

            .sat-clean-quote-result__item.is-total{
                background:linear-gradient(180deg, #f7faff 0%, #eef4ff 100%);
                border-color:#cfe0ff;
            }

            .sat-clean-quote-result__discount,
            .sat-clean-quote-result__note,
            .sat-clean-quote-result__warning{
                padding:12px 14px;
                border-radius:14px;
                font-size:12px;
                line-height:1.55;
                font-weight:600;
            }

            .sat-clean-quote-result__discount{
                background:#f5faff;
                border:1px solid #dbe8ff;
                color:#244c93;
            }

            .sat-clean-quote-result__note{
                background:#f8fafc;
                border:1px solid #e6edf6;
                color:#55657f;
            }

            .sat-clean-quote-result__warning{
                background:#fff8ef;
                border:1px solid #ffd9a3;
                color:#a15c00;
            }

            .sat-clean-modal__actions--spread{
                justify-content:space-between;
                align-items:center;
                gap:14px;
                flex-wrap:wrap;
            }

            .sat-clean-quote-modal__left-actions,
            .sat-clean-quote-modal__right-actions{
                display:flex;
                align-items:center;
                gap:10px;
                flex-wrap:wrap;
            }

            sat-clean-btn.is-loading{
                pointer-events:none;
                opacity:.92;
            }

            .sat-clean-btn__spinner{
                width:14px;
                height:14px;
                border-radius:999px;
                border:2px solid currentColor;
                border-right-color:transparent;
                display:inline-block;
                animation:sat-clean-btn-spin .7s linear infinite;
                flex:0 0 auto;
            }

            @keyframes sat-clean-btn-spin{
                from{ transform:rotate(0deg); }
                to{ transform:rotate(360deg); }
            }

            @media (max-width: 991px){
                .sat-clean-quote-rfc-picker__top{
                    grid-template-columns:1fr;
                }

                .sat-clean-quote-rfc-picker__list{
                    grid-template-columns:1fr;
                }

                .sat-clean-quote-resume__grid{
                    grid-template-columns:repeat(2, minmax(0, 1fr));
                }

                .sat-clean-quote-result__grid{
                    grid-template-columns:repeat(2, minmax(0, 1fr));
                }
            }

            @media (max-width: 767px){
                .sat-clean-quote-modal__dialog{
                    width:min(100vw - 18px, 100%);
                }

                .sat-clean-quote-resume__grid,
                .sat-clean-quote-result__grid{
                    grid-template-columns:1fr;
                }

                .sat-clean-modal__actions--spread,
                .sat-clean-quote-modal__left-actions,
                .sat-clean-quote-modal__right-actions{
                    width:100%;
                }

                .sat-clean-quote-modal__left-actions .sat-clean-btn,
                .sat-clean-quote-modal__right-actions .sat-clean-btn{
                    width:100%;
                }
            }
        `;

        document.head.appendChild(style);
    }
});