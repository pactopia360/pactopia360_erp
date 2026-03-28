/* public/assets/admin/js/pages/sat-ops-downloads.js */

document.addEventListener('DOMContentLoaded', function () {
    function setupSelection(config) {
        const master = document.querySelector(config.master);
        const checks = Array.from(document.querySelectorAll(config.items));
        const counter = document.querySelector(config.counter);
        const actionButton = document.querySelector(config.runButton);
        const actionSelect = document.querySelector(config.actionSelect);

        function update() {
            const selected = checks.filter(cb => cb.checked);

            if (counter) {
                counter.textContent = String(selected.length);
            }

            checks.forEach(function (cb) {
                const tr = cb.closest('tr');
                if (!tr) return;
                tr.classList.toggle('is-selected', cb.checked);
            });

            if (master) {
                const allChecked = checks.length > 0 && selected.length === checks.length;
                const someChecked = selected.length > 0 && selected.length < checks.length;
                master.checked = allChecked;
                master.indeterminate = someChecked;
            }
        }

        if (master) {
            master.addEventListener('change', function () {
                checks.forEach(function (cb) {
                    cb.checked = master.checked;
                });
                update();
            });
        }

        checks.forEach(function (cb) {
            cb.addEventListener('change', update);
        });

        if (actionButton) {
            actionButton.addEventListener('click', function () {
                const selected = checks
                    .filter(function (cb) {
                        return cb.checked;
                    })
                    .map(function (cb) {
                        return cb.value;
                    });

                if (!selected.length) {
                    alert('Selecciona al menos un registro.');
                    return;
                }

                const action = actionSelect ? actionSelect.value : '';
                const form = document.querySelector(config.form);

                if (!form) {
                    alert('No se encontró el formulario masivo.');
                    return;
                }

                Array.from(form.querySelectorAll('.js-bulk-dynamic')).forEach(function (node) {
                    node.remove();
                });

                if (config.hiddenModeName) {
                    const hiddenMode = document.createElement('input');
                    hiddenMode.type = 'hidden';
                    hiddenMode.name = config.hiddenModeName;
                    hiddenMode.value = action;
                    hiddenMode.className = 'js-bulk-dynamic';
                    form.appendChild(hiddenMode);
                }

                selected.forEach(function (value) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = config.inputName;
                    input.value = value;
                    input.className = 'js-bulk-dynamic';
                    form.appendChild(input);
                });

                form.action = config.actionUrl;

                const confirmMessage = typeof config.confirm === 'function'
                    ? config.confirm(selected.length, action)
                    : '¿Seguro que deseas aplicar esta acción a los registros seleccionados?';

                if (!confirm(confirmMessage)) {
                    return;
                }

                form.submit();
            });
        }

        update();
    }

    setupSelection({
        master: '#checkAllFiles',
        items: '.file-row-check',
        counter: '[data-selected-files]',
        runButton: '#runFilesBulkAction',
        actionSelect: '#file_bulk_action',
        form: '#filesBulkForm',
        actionUrl: window.p360SatOpsDownloads.bulkFilesDeleteUrl || '',
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
        actionUrl: window.p360SatOpsDownloads.bulkCfdiDeleteUrl || '',
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
        actionUrl: window.p360SatOpsDownloads.bulkMetadataRecordsDeleteUrl || '',
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
        actionUrl: window.p360SatOpsDownloads.bulkReportRecordsDeleteUrl || '',
        inputName: 'selected_report_records[]',
        confirm: function (count) {
            return '¿Seguro que deseas eliminar ' + count + ' registro(s) de reporte del portal?';
        }
    });
});