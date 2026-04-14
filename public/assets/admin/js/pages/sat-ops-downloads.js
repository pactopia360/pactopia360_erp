/* public/assets/admin/js/pages/sat-ops-downloads.js */
/* Pactopia360 · Admin SAT Ops Descargas · Rediseño mejorado */

document.addEventListener('DOMContentLoaded', function () {
    const appConfig = window.p360SatOpsDownloads || {};

    function q(selector, scope) {
        return (scope || document).querySelector(selector);
    }

    function qa(selector, scope) {
        return Array.from((scope || document).querySelectorAll(selector));
    }

    function createHiddenInput(name, value) {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = name;
        input.value = value;
        input.className = 'js-bulk-dynamic';
        return input;
    }

    function safeConfirm(message) {
        return window.confirm(message);
    }

    function safeAlert(message) {
        window.alert(message);
    }

    function updateSelectedRowStyles(checks) {
        checks.forEach(function (cb) {
            const tr = cb.closest('tr');
            if (!tr) {
                return;
            }
            tr.classList.toggle('is-selected', cb.checked);
        });
    }

    function syncMasterCheckbox(master, checks) {
        if (!master) {
            return;
        }

        const selectedCount = checks.filter(function (cb) {
            return cb.checked;
        }).length;

        const allChecked = checks.length > 0 && selectedCount === checks.length;
        const someChecked = selectedCount > 0 && selectedCount < checks.length;

        master.checked = allChecked;
        master.indeterminate = someChecked;
    }

    function setupSelection(config) {
        const master = q(config.master);
        const counter = q(config.counter);
        const actionButton = q(config.runButton);
        const actionSelect = config.actionSelect ? q(config.actionSelect) : null;
        const form = q(config.form);

        function getChecks() {
            return qa(config.items);
        }

        function getSelectedChecks() {
            return getChecks().filter(function (cb) {
                return cb.checked;
            });
        }

        function update() {
            const checks = getChecks();
            const selected = getSelectedChecks();

            if (counter) {
                counter.textContent = String(selected.length);
            }

            updateSelectedRowStyles(checks);
            syncMasterCheckbox(master, checks);

            if (actionButton) {
                actionButton.disabled = selected.length === 0;
                actionButton.classList.toggle('is-disabled', selected.length === 0);
            }
        }

        if (master) {
            master.addEventListener('change', function () {
                const checks = getChecks();
                checks.forEach(function (cb) {
                    cb.checked = master.checked;
                });
                update();
            });
        }

        function bindRowChecks() {
            getChecks().forEach(function (cb) {
                cb.addEventListener('change', update);
            });
        }

        bindRowChecks();

        if (actionButton) {
            actionButton.addEventListener('click', function () {
                const checks = getChecks();
                const selected = checks
                    .filter(function (cb) {
                        return cb.checked;
                    })
                    .map(function (cb) {
                        return cb.value;
                    });

                if (!selected.length) {
                    safeAlert('Selecciona al menos un registro.');
                    return;
                }

                if (!form) {
                    safeAlert('No se encontró el formulario masivo.');
                    return;
                }

                if (!config.actionUrl) {
                    safeAlert('No se encontró la URL de acción masiva.');
                    return;
                }

                const action = actionSelect ? actionSelect.value : '';

                qa('.js-bulk-dynamic', form).forEach(function (node) {
                    node.remove();
                });

                qa('input[name="_method"]', form).forEach(function (node) {
                    node.remove();
                });

                if (config.hiddenModeName) {
                    form.appendChild(createHiddenInput(config.hiddenModeName, action));
                }

                selected.forEach(function (value) {
                    form.appendChild(createHiddenInput(config.inputName, value));
                });

                form.action = config.actionUrl;

                const confirmMessage = typeof config.confirm === 'function'
                    ? config.confirm(selected.length, action)
                    : '¿Seguro que deseas aplicar esta acción a los registros seleccionados?';

                if (!safeConfirm(confirmMessage)) {
                    return;
                }

                actionButton.disabled = true;
                form.submit();
            });
        }

        update();

        return {
            update: update
        };
    }

    function setupAccordionControls() {
        const accordions = qa('.ops-accordion');
        const expandAll = q('[data-expand-all]');
        const collapseAll = q('[data-collapse-all]');

        if (expandAll) {
            expandAll.addEventListener('click', function () {
                accordions.forEach(function (accordion) {
                    accordion.setAttribute('open', 'open');
                    accordion.classList.add('is-open');
                });
            });
        }

        if (collapseAll) {
            collapseAll.addEventListener('click', function () {
                accordions.forEach(function (accordion) {
                    accordion.removeAttribute('open');
                    accordion.classList.remove('is-open');
                });
            });
        }
    }

    function setupQuickNav() {
        const links = qa('.ops-quick-nav a[href^="#"]');

        links.forEach(function (link) {
            link.addEventListener('click', function (event) {
                const hash = link.getAttribute('href');

                if (!hash || hash === '#') {
                    return;
                }

                const target = q(hash);

                if (!target) {
                    return;
                }

                event.preventDefault();

                if (target.tagName && target.tagName.toLowerCase() === 'details') {
                    target.setAttribute('open', 'open');
                    target.classList.add('is-open');
                }

                setTimeout(function () {
                    const headerOffset = 16;
                    const elementPosition = target.getBoundingClientRect().top + window.scrollY;
                    const offsetPosition = Math.max(elementPosition - headerOffset, 0);

                    window.scrollTo({
                        top: offsetPosition,
                        behavior: 'smooth'
                    });
                }, 70);
            });
        });
    }

    function setupDetailsAutoClose() {
        const inlineEditors = qa('.ops-inline-editor');

        document.addEventListener('click', function (event) {
            inlineEditors.forEach(function (detail) {
                if (!detail.open) {
                    return;
                }

                if (!detail.contains(event.target)) {
                    detail.removeAttribute('open');
                }
            });
        });
    }

    function setupAccordionStateHint() {
        const accordions = qa('.ops-accordion');

        accordions.forEach(function (accordion) {
            const summary = q('.ops-accordion__summary', accordion);

            function syncState() {
                accordion.classList.toggle('is-open', accordion.open);
            }

            if (summary) {
                summary.addEventListener('click', function () {
                    window.requestAnimationFrame(syncState);
                });
            }

            accordion.addEventListener('toggle', syncState);
            syncState();
        });
    }

    function setupInlineEditorSingleOpen() {
        const editors = qa('.ops-inline-editor');

        editors.forEach(function (editor) {
            editor.addEventListener('toggle', function () {
                if (!editor.open) {
                    return;
                }

                editors.forEach(function (other) {
                    if (other !== editor && other.open) {
                        other.removeAttribute('open');
                    }
                });
            });
        });
    }

    function setupStickyHeaderShadow() {
        const tables = qa('.ops-table-wrap');

        tables.forEach(function (wrap) {
            function syncShadow() {
                wrap.classList.toggle('is-scrolled-x', wrap.scrollLeft > 8);
            }

            wrap.addEventListener('scroll', syncShadow, { passive: true });
            syncShadow();
        });
    }

    function setupAutoDismissAlerts() {
        const alerts = qa('.ops-alert');

        alerts.forEach(function (alertBox) {
            setTimeout(function () {
                alertBox.classList.add('is-hiding');

                setTimeout(function () {
                    if (alertBox && alertBox.parentNode) {
                        alertBox.parentNode.removeChild(alertBox);
                    }
                }, 260);
            }, 4500);
        });
    }

    function setupButtonSafetyState() {
        qa('#runFilesBulkAction, #runCfdiBulkAction, #runMetadataRecordsBulkDelete, #runReportRecordsBulkDelete')
            .forEach(function (button) {
                button.disabled = true;
                button.classList.add('is-disabled');
            });
    }

    setupButtonSafetyState();

    setupSelection({
        master: '#checkAllFiles',
        items: '.file-row-check',
        counter: '[data-selected-files]',
        runButton: '#runFilesBulkAction',
        actionSelect: '#file_bulk_action',
        form: '#filesBulkForm',
        actionUrl: appConfig.bulkFilesDeleteUrl || '',
        inputName: 'selected_files[]',
        confirm: function (count) {
            return '¿Seguro que deseas eliminar ' + count + ' archivo(s) seleccionados? Esta acción también eliminará el archivo físico cuando exista.';
        }
    });

    setupSelection({
        master: '#checkAllCfdi',
        items: '.cfdi-row-check',
        counter: '[data-selected-cfdi]',
        runButton: '#runCfdiBulkAction',
        actionSelect: '#cfdi_bulk_mode',
        form: '#cfdiBulkForm',
        actionUrl: appConfig.bulkCfdiDeleteUrl || '',
        inputName: 'selected_cfdi[]',
        hiddenModeName: 'mode',
        confirm: function (count, mode) {
            return mode === 'with_files'
                ? '¿Seguro que deseas eliminar ' + count + ' CFDI(s) seleccionados y también sus archivos relacionados cuando aplique?'
                : '¿Seguro que deseas eliminar ' + count + ' CFDI(s) seleccionados solo del índice?';
        }
    });

    setupSelection({
        master: '#checkAllMetadataRecords',
        items: '.metadata-record-row-check',
        counter: '[data-selected-metadata-records]',
        runButton: '#runMetadataRecordsBulkDelete',
        form: '#metadataRecordsBulkForm',
        actionUrl: appConfig.bulkMetadataRecordsDeleteUrl || '',
        inputName: 'selected_metadata_records[]',
        confirm: function (count) {
            return '¿Seguro que deseas eliminar ' + count + ' registro(s) de metadata del portal?';
        }
    });

    setupSelection({
        master: '#checkAllReportRecords',
        items: '.report-record-row-check',
        counter: '[data-selected-report-records]',
        runButton: '#runReportRecordsBulkDelete',
        form: '#reportRecordsBulkForm',
        actionUrl: appConfig.bulkReportRecordsDeleteUrl || '',
        inputName: 'selected_report_records[]',
        confirm: function (count) {
            return '¿Seguro que deseas eliminar ' + count + ' registro(s) de reporte del portal?';
        }
    });

        function setupPaidQuoteUploadForm() {
        const paidQuotes = Array.isArray(appConfig.paidQuotes) ? appConfig.paidQuotes : [];
        const form = q('#paidQuoteUploadForm');
        const modeInput = q('#manual_mode');
        const modeButtons = qa('[data-upload-mode]');
        const quoteSearchInput = q('#quote_search');
        const quoteSelect = q('#quote_id');
        const rfcInput = q('#customer_rfc');
        const uploadTypeSelect = q('#upload_type');
        const targetVaultSelect = q('#target_vault');
        const directionSelect = q('#direction');
        const filesInput = q('#files');
        const summaryBox = q('#quoteUploadSummary');
        const submitButton = q('#manualUploadSubmit');
        const modeNotice = q('#manualModeNotice');
        const manualOriginReadonly = q('#manualOriginReadonly');

        const profileReferenceInput = q('#profile_reference');
        const adminNotesInput = q('#admin_notes');

        const replaceTypeSelect = q('#replace_type');
        const replaceIdInput = q('#replace_id');
        const replacementReasonInput = q('#replacement_reason');

        if (!form || !quoteSelect || !modeInput) {
            return;
        }

        const actionQuote = form.dataset.actionQuote || appConfig.uploadFromQuoteUrl || form.action || '';
        const actionProfile = form.dataset.actionProfile || appConfig.uploadFromProfileUrl || actionQuote;
        const actionReplace = form.dataset.actionReplace || appConfig.replaceUploadUrl || actionQuote;

        let currentMode = String(modeInput.value || 'quote').trim() || 'quote';

        const originalOptions = Array.from(quoteSelect.options).map(function (option) {
            return {
                value: option.value,
                text: option.textContent,
                dataset: {
                    rfc: option.dataset.rfc || '',
                    folio: option.dataset.folio || '',
                    customer: option.dataset.customer || '',
                    sourceTable: option.dataset.sourceTable || '',
                    search: option.dataset.search || ''
                }
            };
        });

        function getModeLabel(value) {
            if (value === 'quote') return 'Cotización pagada';
            if (value === 'profile') return 'Carga directa al perfil';
            if (value === 'replace') return 'Reemplazo de carga existente';
            return 'Sin modo';
        }

        function getTypeLabel(value) {
            if (value === 'xml') return 'XML';
            if (value === 'metadata') return 'Metadata';
            if (value === 'report') return 'Reporte';
            return 'Sin tipo';
        }

        function getVaultLabel(value) {
            if (value === 'v1') return 'V1';
            if (value === 'v2') return 'V2';
            return 'Sin destino';
        }

        function getDirectionLabel(value) {
            if (value === 'emitidos') return 'Emitidos';
            if (value === 'recibidos') return 'Recibidos';
            return 'Sin definir';
        }

        function getSelectedOption() {
            return quoteSelect.options[quoteSelect.selectedIndex] || null;
        }

        function getSelectedQuote() {
            const selectedId = String(quoteSelect.value || '').trim();

            if (selectedId === '') {
                return null;
            }

            return paidQuotes.find(function (item) {
                return String(item.id || '') === selectedId;
            }) || null;
        }

        function rebuildQuoteOptions(filteredOptions, preserveValue) {
            const selectedValue = String(preserveValue || quoteSelect.value || '').trim();

            quoteSelect.innerHTML = '';

            const defaultOption = document.createElement('option');
            defaultOption.value = '';
            defaultOption.textContent = 'Seleccionar cotización pagada';
            quoteSelect.appendChild(defaultOption);

            filteredOptions.forEach(function (item) {
                const option = document.createElement('option');
                option.value = item.value;
                option.textContent = item.text;
                option.dataset.rfc = item.dataset.rfc;
                option.dataset.folio = item.dataset.folio;
                option.dataset.customer = item.dataset.customer;
                option.dataset.sourceTable = item.dataset.sourceTable;
                option.dataset.search = item.dataset.search;

                if (selectedValue !== '' && selectedValue === String(item.value)) {
                    option.selected = true;
                }

                quoteSelect.appendChild(option);
            });
        }

        function filterQuoteOptions() {
            if (!quoteSearchInput) {
                return;
            }

            const term = String(quoteSearchInput.value || '').trim().toLowerCase();

            if (term === '') {
                rebuildQuoteOptions(originalOptions);
                return;
            }

            const filtered = originalOptions.filter(function (item) {
                const haystack = String(item.dataset.search || item.text || item.value || '').toLowerCase();
                return haystack.includes(term);
            });

            rebuildQuoteOptions(filtered);
        }

        function syncRfcFromQuote(force) {
            if (currentMode !== 'quote' || !rfcInput) {
                return;
            }

            const option = getSelectedOption();
            if (!option) {
                return;
            }

            const autoRfc = String(option.dataset.rfc || '').trim();
            const currentRfc = String(rfcInput.value || '').trim();

            if ((force || currentRfc === '') && autoRfc !== '') {
                rfcInput.value = autoRfc;
            }
        }

        function setScopeVisibility(mode) {
            qa('[data-mode-scope]').forEach(function (node) {
                const scope = node.getAttribute('data-mode-scope') || '';
                const shouldShow = scope === mode;

                node.classList.toggle('ops-upload-admin__is-hidden', !shouldShow);

                qa('input, select, textarea', node).forEach(function (field) {
                    if (field.id === 'quote_id') {
                        field.required = shouldShow && mode === 'quote';
                        return;
                    }

                    if (field.id === 'replace_type' || field.id === 'replace_id') {
                        field.required = shouldShow && mode === 'replace';
                        return;
                    }

                    if (field.name === 'customer_rfc') {
                        return;
                    }

                    field.required = false;
                });
            });
        }

        function setFormAction(mode) {
            if (mode === 'profile') {
                form.action = actionProfile;
                return;
            }

            if (mode === 'replace') {
                form.action = actionReplace;
                return;
            }

            form.action = actionQuote;
        }

        function setModeNotice(mode) {
            if (!modeNotice) {
                return;
            }

            if (mode === 'profile') {
                modeNotice.textContent = 'Modo perfil: registra archivos directamente a un RFC/perfil sin depender de una cotización.';
                return;
            }

            if (mode === 'replace') {
                modeNotice.textContent = 'Modo reemplazo: indica el tipo e ID existente que será sustituido por esta nueva carga.';
                return;
            }

            modeNotice.textContent = 'Modo cotización: selecciona una cotización pagada válida antes de registrar la carga.';
        }

        function setOriginReadonly(mode) {
            if (!manualOriginReadonly) {
                return;
            }

            if (mode === 'profile') {
                manualOriginReadonly.textContent = 'Carga directa al perfil';
                return;
            }

            if (mode === 'replace') {
                manualOriginReadonly.textContent = 'Reemplazo administrativo';
                return;
            }

            manualOriginReadonly.textContent = 'Cotización pagada';
        }

        function setSubmitLabel(mode) {
            if (!submitButton) {
                return;
            }

            if (mode === 'profile') {
                submitButton.textContent = 'Registrar al perfil';
                return;
            }

            if (mode === 'replace') {
                submitButton.textContent = 'Registrar reemplazo';
                return;
            }

            submitButton.textContent = 'Registrar carga';
        }

        function setMode(mode) {
            currentMode = ['quote', 'profile', 'replace'].includes(mode) ? mode : 'quote';
            modeInput.value = currentMode;

            modeButtons.forEach(function (button) {
                button.classList.toggle('is-active', button.getAttribute('data-upload-mode') === currentMode);
            });

            setScopeVisibility(currentMode);
            setFormAction(currentMode);
            setModeNotice(currentMode);
            setOriginReadonly(currentMode);
            setSubmitLabel(currentMode);

            if (currentMode !== 'quote') {
                quoteSelect.value = '';
            }

            renderSummary();
        }

        function renderSummary() {
            if (!summaryBox) {
                return;
            }

            const modeLabel = getModeLabel(currentMode);
            const typeLabel = getTypeLabel(uploadTypeSelect ? uploadTypeSelect.value : '');
            const vaultLabel = getVaultLabel(targetVaultSelect ? targetVaultSelect.value : '');
            const directionLabel = getDirectionLabel(directionSelect ? directionSelect.value : '');
            const filesCount = filesInput && filesInput.files ? filesInput.files.length : 0;
            const rfc = String(rfcInput && rfcInput.value ? rfcInput.value : '').trim();

            if (currentMode === 'quote') {
                const option = getSelectedOption();
                const quote = getSelectedQuote();

                if (!option || String(quoteSelect.value || '').trim() === '') {
                    summaryBox.textContent = 'Modo: ' + modeLabel + ' · selecciona una cotización pagada para ver el resumen.';
                    return;
                }

                const folio = String(option.dataset.folio || (quote && quote.folio) || 'Sin folio').trim();
                const customer = String(option.dataset.customer || (quote && quote.customer) || 'Sin cliente').trim();
                const sourceTable = String(option.dataset.sourceTable || (quote && quote.source_table) || '').trim();
                const quoteRfc = String(option.dataset.rfc || (quote && quote.rfc) || '').trim();

                summaryBox.textContent =
                    'Modo: ' + modeLabel +
                    ' · Folio: ' + folio +
                    ' · RFC: ' + (rfc || quoteRfc || 'Sin RFC') +
                    ' · Cliente: ' + customer +
                    (sourceTable !== '' ? ' · Origen: ' + sourceTable : '') +
                    ' · Tipo: ' + typeLabel +
                    ' · Bóveda: ' + vaultLabel +
                    ' · Dirección: ' + directionLabel +
                    ' · Archivos: ' + filesCount;
                return;
            }

            if (currentMode === 'profile') {
                const reference = String(profileReferenceInput && profileReferenceInput.value ? profileReferenceInput.value : '').trim();
                const notes = String(adminNotesInput && adminNotesInput.value ? adminNotesInput.value : '').trim();

                summaryBox.textContent =
                    'Modo: ' + modeLabel +
                    ' · RFC destino: ' + (rfc || 'Pendiente') +
                    ' · Referencia: ' + (reference || 'Sin referencia') +
                    ' · Tipo: ' + typeLabel +
                    ' · Bóveda: ' + vaultLabel +
                    ' · Dirección: ' + directionLabel +
                    ' · Archivos: ' + filesCount +
                    (notes !== '' ? ' · Nota: ' + notes : '');
                return;
            }

            const replaceType = String(replaceTypeSelect && replaceTypeSelect.value ? replaceTypeSelect.value : '').trim();
            const replaceId = String(replaceIdInput && replaceIdInput.value ? replaceIdInput.value : '').trim();
            const reason = String(replacementReasonInput && replacementReasonInput.value ? replacementReasonInput.value : '').trim();

            summaryBox.textContent =
                'Modo: ' + modeLabel +
                ' · Reemplaza: ' + (replaceType || 'Sin tipo') +
                ' #' + (replaceId || 'Pendiente') +
                ' · RFC destino: ' + (rfc || 'Pendiente') +
                ' · Tipo nuevo: ' + typeLabel +
                ' · Bóveda: ' + vaultLabel +
                ' · Dirección: ' + directionLabel +
                ' · Archivos: ' + filesCount +
                (reason !== '' ? ' · Motivo: ' + reason : '');
        }

        modeButtons.forEach(function (button) {
            button.addEventListener('click', function () {
                const mode = button.getAttribute('data-upload-mode') || 'quote';
                setMode(mode);
            });
        });

        if (quoteSearchInput) {
            quoteSearchInput.addEventListener('input', function () {
                const previousValue = quoteSelect.value;
                filterQuoteOptions();
                if (previousValue !== '') {
                    quoteSelect.value = previousValue;
                }
                renderSummary();
            });
        }

        quoteSelect.addEventListener('change', function () {
            syncRfcFromQuote(false);
            renderSummary();
        });

        if (rfcInput) {
            rfcInput.addEventListener('input', renderSummary);
        }

        if (uploadTypeSelect) {
            uploadTypeSelect.addEventListener('change', renderSummary);
        }

        if (targetVaultSelect) {
            targetVaultSelect.addEventListener('change', renderSummary);
        }

        if (directionSelect) {
            directionSelect.addEventListener('change', renderSummary);
        }

        if (filesInput) {
            filesInput.addEventListener('change', renderSummary);
        }

        if (profileReferenceInput) {
            profileReferenceInput.addEventListener('input', renderSummary);
        }

        if (adminNotesInput) {
            adminNotesInput.addEventListener('input', renderSummary);
        }

        if (replaceTypeSelect) {
            replaceTypeSelect.addEventListener('change', renderSummary);
        }

        if (replaceIdInput) {
            replaceIdInput.addEventListener('input', renderSummary);
        }

        if (replacementReasonInput) {
            replacementReasonInput.addEventListener('input', renderSummary);
        }

        form.addEventListener('submit', function (event) {
            const uploadType = uploadTypeSelect ? String(uploadTypeSelect.value || '').trim() : '';
            const targetVault = targetVaultSelect ? String(targetVaultSelect.value || '').trim() : '';
            const hasFiles = !!(filesInput && filesInput.files && filesInput.files.length > 0);
            const quoteId = String(quoteSelect.value || '').trim();
            const rfcValue = String(rfcInput && rfcInput.value ? rfcInput.value : '').trim();
            const replaceType = String(replaceTypeSelect && replaceTypeSelect.value ? replaceTypeSelect.value : '').trim();
            const replaceId = String(replaceIdInput && replaceIdInput.value ? replaceIdInput.value : '').trim();

            if (uploadType === '') {
                event.preventDefault();
                safeAlert('Debes seleccionar el tipo de carga.');
                uploadTypeSelect && uploadTypeSelect.focus();
                return;
            }

            if (targetVault === '') {
                event.preventDefault();
                safeAlert('Debes seleccionar la bóveda destino.');
                targetVaultSelect && targetVaultSelect.focus();
                return;
            }

            if (!hasFiles) {
                event.preventDefault();
                safeAlert('Debes seleccionar al menos un archivo.');
                filesInput && filesInput.focus();
                return;
            }

            if (currentMode === 'quote') {
                if (quoteId === '') {
                    event.preventDefault();
                    safeAlert('Debes seleccionar una cotización pagada.');
                    quoteSelect.focus();
                    return;
                }

                if (rfcValue === '') {
                    syncRfcFromQuote(true);
                }

                return;
            }

            if (rfcValue === '') {
                event.preventDefault();
                safeAlert('Debes indicar el RFC destino.');
                rfcInput && rfcInput.focus();
                return;
            }

            if (currentMode === 'profile') {
                form.action = actionProfile;
                return;
            }

            if (replaceType === '') {
                event.preventDefault();
                safeAlert('Debes seleccionar el tipo de carga a reemplazar.');
                replaceTypeSelect && replaceTypeSelect.focus();
                return;
            }

            if (replaceId === '') {
                event.preventDefault();
                safeAlert('Debes indicar el ID del registro a reemplazar.');
                replaceIdInput && replaceIdInput.focus();
                return;
            }

            form.action = actionReplace;
        });

        rebuildQuoteOptions(originalOptions);
        setMode(currentMode);
        syncRfcFromQuote(false);
        renderSummary();
    }

    setupPaidQuoteUploadForm();
    setupAccordionControls();
    setupQuickNav();
    setupDetailsAutoClose();
    setupAccordionStateHint();
    setupInlineEditorSingleOpen();
    setupStickyHeaderShadow();
    setupAutoDismissAlerts();
});