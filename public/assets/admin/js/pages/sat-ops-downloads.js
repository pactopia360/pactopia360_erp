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

    setupAccordionControls();
    setupQuickNav();
    setupDetailsAutoClose();
    setupAccordionStateHint();
    setupInlineEditorSingleOpen();
    setupStickyHeaderShadow();
    setupAutoDismissAlerts();
});