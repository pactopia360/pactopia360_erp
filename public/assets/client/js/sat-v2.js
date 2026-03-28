document.addEventListener('DOMContentLoaded', function () {
    const $ = (selector, root = document) => root.querySelector(selector);
    const $$ = (selector, root = document) => Array.from(root.querySelectorAll(selector));

    const loading = $('#sv2Loading');
    const toggleBtn = $('#toggleMetadata');
    const metadataBlock = $('#metadataBlock');
    const toggleXmlBtn = $('#toggleXml');
    const xmlBlock = $('#xmlBlock');
    const toggleReportBtn = $('#toggleReport');
    const reportBlock = $('#reportBlock');
    const toggleDownloadsBtn = $('#toggleDownloads');
    const downloadsBlock = $('#downloadsBlock');
    const toggleFiscalBtn = $('#toggleFiscal');
    const fiscalBlock = $('#fiscalBlock');
    const uploadForms = $$('[data-sv2-upload-form]');
    const toggleDataLoadBtn = $('#toggleDataLoad');
    const dataLoadBlock = $('#dataLoadBlock');
    const reprocessAdvancedForm = $('#sv2ReprocessAdvancedForm');
    const reprocessPreviewBox = $('#sv2ReprocessPreview');
    const fiscalTrendChartEl = $('#sv2FiscalTrendChart');
    const fiscalMixChartEl = $('#sv2FiscalMixChart');
    const sectionDock = $('#sv2SectionDock');
    const dockObserver = null;
    const sectionDockLinks = $$('[data-sv2-jump]');
    const expandAllBtn = $('#sv2ExpandAll');
    const collapseAllBtn = $('#sv2CollapseAll');

    let loadingTimer = null;
    let loadingSeconds = 0;
    let sv2TrendChart = null;
    let sv2MixChart = null;
    let sv2LastReprocessPreview = null;
    let sv2FiscalChartsBooted = false;
    let sv2FiscalChartsRefreshTimer = null;

    function syncCollapsedIcon(button, block) {
        if (!button || !block) return;
        const icon = $('.sv2MetaBar__icon', button);
        if (!icon) return;
        icon.textContent = block.classList.contains('sv2Section--collapsed') ? '+' : '−';
    }

    syncCollapsedIcon(toggleBtn, metadataBlock);
    syncCollapsedIcon(toggleXmlBtn, xmlBlock);
    syncCollapsedIcon(toggleReportBtn, reportBlock);
    syncCollapsedIcon(toggleDownloadsBtn, downloadsBlock);
    syncCollapsedIcon(toggleFiscalBtn, fiscalBlock);

    function setBodyModalState(isOpen) {
        document.body.classList.toggle('sv2-modal-open', isOpen);
    }

    function openModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;

        modal.classList.add('is-open');
        modal.setAttribute('aria-hidden', 'false');
        setBodyModalState(true);
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (!modal) return;

        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.sv2Modal.is-open') && !(loading && loading.classList.contains('is-open'))) {
            setBodyModalState(false);
        }
    }

    function closeAllModals() {
        $$('.sv2Modal.is-open').forEach(function (modal) {
            modal.classList.remove('is-open');
            modal.setAttribute('aria-hidden', 'true');
        });

        if (!(loading && loading.classList.contains('is-open'))) {
            setBodyModalState(false);
        }
    }

    function stopLoadingTimer() {
        if (loadingTimer) {
            clearInterval(loadingTimer);
            loadingTimer = null;
        }
    }

    function setLoadingTexts(type) {
        if (!loading) return;

        const title = $('.sv2Loading__title', loading);
        const text = $('.sv2Loading__text', loading);

        if (type === 'xml') {
            if (title) title.textContent = 'Cargando XML...';
            if (text) text.textContent = 'Estamos subiendo el XML y asociándolo al RFC y al lote metadata seleccionado.';
            return;
        }

        if (type === 'report') {
            if (title) title.textContent = 'Cargando reporte...';
            if (text) text.textContent = 'Estamos subiendo el reporte y relacionándolo con el RFC y las cargas seleccionadas.';
            return;
        }

        if (title) title.textContent = 'Cargando metadata...';
        if (text) text.textContent = 'Estamos subiendo el archivo y registrando el lote para el RFC seleccionado.';
    }

    function updateLoadingStatus(stageText, hintText) {
        const stage = $('#sv2LoadingStage');
        const elapsed = $('#sv2LoadingElapsed');
        const hint = $('#sv2LoadingHint');

        if (stage && stageText) stage.textContent = stageText;
        if (hint && hintText) hint.textContent = hintText;
        if (elapsed) elapsed.textContent = loadingSeconds + 's';
    }

    function showLoading(type) {
        if (!loading) return;

        loading.classList.add('is-open');
        loading.setAttribute('aria-hidden', 'false');
        setBodyModalState(true);

        loadingSeconds = 0;
        setLoadingTexts(type);
        updateLoadingStatus('Preparando envío', 'Si el archivo es pesado, el proceso puede tardar varios minutos.');

        stopLoadingTimer();
        loadingTimer = setInterval(function () {
            loadingSeconds++;

            let stageText = 'Seguimos trabajando';
            if (loadingSeconds < 4) {
                stageText = 'Conectando con servidor';
            } else if (loadingSeconds < 10) {
                stageText = 'Esperando respuesta del servidor';
            } else if (loadingSeconds < 20) {
                stageText = 'Procesando archivo';
            }

            const hintText = loadingSeconds >= 12
                ? 'El servidor sigue procesando la carga. ZIP y archivos grandes pueden tardar más.'
                : null;

            updateLoadingStatus(stageText, hintText);
        }, 1000);
    }

    function hideLoading() {
        stopLoadingTimer();

        if (!loading) return;

        loading.classList.remove('is-open');
        loading.setAttribute('aria-hidden', 'true');

        if (!document.querySelector('.sv2Modal.is-open')) {
            setBodyModalState(false);
        }
    }

    function setFormDisabled(form, disabled) {
        $$('button, input, select, textarea', form).forEach(function (el) {
            if (el.type === 'hidden') return;
            el.disabled = disabled;
        });
    }

    function showAjaxError(xhr, response) {
        if (response && response.message) {
            alert(response.message);
            return;
        }

        if (response && response.errors) {
            const firstKey = Object.keys(response.errors)[0];
            if (firstKey && response.errors[firstKey] && response.errors[firstKey][0]) {
                alert(response.errors[firstKey][0]);
                return;
            }
        }

        let raw = (xhr.responseText || '').trim();
        if (raw.length > 500) {
            raw = raw.substring(0, 500) + '...';
        }

        alert('Ocurrió un error al cargar el archivo.\n\n' + (raw || 'Revisa el log del servidor.'));
    }

        function moneyFormat(value) {
        const safe = Number(value || 0);
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN',
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        }).format(safe);
    }

    function escapeHtml(value) {
        return String(value || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    function renderReprocessPreview(payload) {
        if (!reprocessPreviewBox) return;

        const data = payload?.data || {};
        const summary = data.scope_summary || {};
        const rows = Array.isArray(data.sample_rows) ? data.sample_rows : [];
        const risk = String(summary.risk_level || 'low').toLowerCase();
        const riskLabel = risk === 'high' ? 'Alto' : (risk === 'medium' ? 'Medio' : 'Bajo');

        const rowsHtml = rows.length
            ? rows.map(function (row) {
                return `
                    <tr>
                        <td>${escapeHtml(row.fecha_emision || '—')}</td>
                        <td>${escapeHtml(row.direction || '—')}</td>
                        <td>${escapeHtml(row.uuid || '—')}</td>
                        <td>${moneyFormat(row.total || 0)}</td>
                    </tr>
                `;
            }).join('')
            : `
                <tr>
                    <td colspan="4">Sin muestra disponible para este alcance.</td>
                </tr>
            `;

        reprocessPreviewBox.innerHTML = `
            <div class="sv2ReprocessPreview__summary">
                <div class="sv2ReprocessPreview__kpi">
                    <span class="sv2ReprocessPreview__label">Alcance</span>
                    <strong>${escapeHtml(summary.label || 'Sin definir')}</strong>
                </div>
                <div class="sv2ReprocessPreview__kpi">
                    <span class="sv2ReprocessPreview__label">CFDI candidatos</span>
                    <strong>${Number(summary.total_candidates || data.total_candidates || 0).toLocaleString('es-MX')}</strong>
                </div>
                <div class="sv2ReprocessPreview__kpi">
                    <span class="sv2ReprocessPreview__label">Lotes estimados</span>
                    <strong>${Number(summary.estimated_batches || data.estimated_batches || 0).toLocaleString('es-MX')}</strong>
                </div>
                <div class="sv2ReprocessPreview__kpi">
                    <span class="sv2ReprocessPreview__label">Riesgo</span>
                    <strong class="sv2RiskBadge sv2RiskBadge--${escapeHtml(risk)}">${riskLabel}</strong>
                </div>
            </div>

            <div class="sv2ReprocessPreview__meta">
                <span><strong>Periodo:</strong> ${escapeHtml(summary.period_ym || '—')}</span>
                <span><strong>Dirección:</strong> ${escapeHtml(summary.direction || 'Todas')}</span>
                <span><strong>Límite:</strong> ${Number(summary.limit || 0).toLocaleString('es-MX')}</span>
                <span><strong>Lote:</strong> ${Number(summary.chunk_size || 0).toLocaleString('es-MX')}</span>
            </div>

            <div class="sv2ReprocessPreview__tableWrap">
                <table class="sv2MiniTable">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Dirección</th>
                            <th>UUID</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${rowsHtml}
                    </tbody>
                </table>
            </div>
        `;
    }

    function renderReprocessError(message, extraHtml) {
        if (!reprocessPreviewBox) return;

        reprocessPreviewBox.innerHTML = `
            <div class="sv2ReprocessPreview__error">
                <div class="sv2ReprocessPreview__errorTitle">No se pudo calcular el preview</div>
                <div class="sv2ReprocessPreview__errorText">${escapeHtml(message || 'Ocurrió un error inesperado.')}</div>
                ${extraHtml || ''}
            </div>
        `;
    }

    function getReprocessFormData() {
        if (!reprocessAdvancedForm) return null;
        return new FormData(reprocessAdvancedForm);
    }

    async function postJsonForm(url, formData, loadingType) {
        closeAllModals();
        showLoading(loadingType);

        try {
            const response = await fetch(url, {
                method: 'POST',
                headers: {
                    'X-CSRF-TOKEN': window.sv2Config?.csrf || '',
                    'X-Requested-With': 'XMLHttpRequest',
                    'Accept': 'application/json'
                },
                body: formData
            });

            const text = await response.text();
            let json = null;

            try {
                json = JSON.parse(text);
            } catch (e) {
                json = null;
            }

            hideLoading();

            if (!response.ok || !json) {
                throw new Error((json && json.message) ? json.message : (text || 'No se recibió una respuesta válida del servidor.'));
            }

            return json;
        } catch (error) {
            hideLoading();
            throw error;
        }
    }

    async function handleReprocessPreview() {
        if (!reprocessAdvancedForm) return;

        const formData = getReprocessFormData();
        if (!formData) return;

        try {
            const previewUrl = window.sv2Config?.fiscalCharts?.routes?.preview;
            if (!previewUrl) {
                alert('No se encontró la ruta de preview de relectura.');
                return;
            }

            const json = await postJsonForm(previewUrl, formData, 'reprocess-preview');
            sv2LastReprocessPreview = json;
            openModal('fiscalReprocessModal');
            renderReprocessPreview(json);
        } catch (error) {
            openModal('fiscalReprocessModal');
            renderReprocessError(error.message || 'No fue posible calcular el preview.');
        }
    }

    async function handleReprocessRun(forceAll) {
        if (!reprocessAdvancedForm) return;

        const formData = getReprocessFormData();
        if (!formData) return;

        if (forceAll) {
            formData.set('force_all', '1');
        }

        try {
            const runUrl = window.sv2Config?.fiscalCharts?.routes?.run || window.sv2Config?.fiscalCharts?.routes?.preview;
            if (!runUrl) {
                alert('No se encontró la ruta de ejecución de relectura.');
                return;
            }

            const json = await postJsonForm(runUrl, formData, 'reprocess-run');

            if (json.ok) {
                alert(json.message || 'Relectura completada correctamente.');
                window.location.href = json.redirect_url || window.location.href;
                return;
            }

            throw new Error(json.message || 'No se pudo ejecutar la relectura.');
        } catch (error) {
            openModal('fiscalReprocessModal');

            if (sv2LastReprocessPreview) {
                renderReprocessPreview(sv2LastReprocessPreview);
            }

            const rawPreview = sv2LastReprocessPreview?.data || {};
            const suggested = rawPreview?.suggested_scope || null;

            const extraHtml = suggested ? `
                <div class="sv2ReprocessPreview__suggestion">
                    <strong>Sugerencia:</strong>
                    usar <em>${escapeHtml(suggested.label || suggested.scope || 'scope sugerido')}</em>
                    ${suggested.period_ym ? ` · periodo ${escapeHtml(suggested.period_ym)}` : ''}
                    ${suggested.limit ? ` · límite ${Number(suggested.limit).toLocaleString('es-MX')}` : ''}
                </div>
            ` : '';

            renderReprocessError(error.message || 'No fue posible ejecutar la relectura.', extraHtml);

            const wantsForceAll = window.confirm(
                'La corrida completa fue bloqueada por seguridad o devolvió advertencia.\n\n' +
                '¿Deseas intentar nuevamente con confirmación forzada?'
            );

            if (wantsForceAll) {
                handleReprocessRun(true);
            }
        }
    }

            function renderFiscalCharts(forceRedraw = false) {
        const chartPayload = window.sv2Config?.fiscalCharts || null;
        const hasApex = typeof window.ApexCharts !== 'undefined';

        if (!chartPayload || !hasApex) {
            return;
        }

        if (!fiscalTrendChartEl && !fiscalMixChartEl) {
            return;
        }

        const labels = Array.isArray(chartPayload.labels) ? chartPayload.labels : [];
        const ingresos = Array.isArray(chartPayload.ingresos) ? chartPayload.ingresos : [];
        const egresos = Array.isArray(chartPayload.egresos) ? chartPayload.egresos : [];
        const pagos = Array.isArray(chartPayload.pagos) ? chartPayload.pagos : [];
        const ivaNeto = Array.isArray(chartPayload.iva_neto) ? chartPayload.iva_neto : [];
        const mix = Array.isArray(chartPayload.mix) ? chartPayload.mix : [];
        const mixLabels = Array.isArray(chartPayload.mix_labels) ? chartPayload.mix_labels : [];

        const trendSeries = [
            { name: 'Ingresos', type: 'area', data: ingresos },
            { name: 'Egresos', type: 'line', data: egresos },
            { name: 'Pagos', type: 'line', data: pagos },
            { name: 'IVA neto', type: 'bar', data: ivaNeto }
        ];

        if (fiscalTrendChartEl && labels.length) {
            if (!sv2TrendChart || forceRedraw) {
                if (sv2TrendChart) {
                    try { sv2TrendChart.destroy(); } catch (e) {}
                    sv2TrendChart = null;
                }

                sv2TrendChart = new window.ApexCharts(fiscalTrendChartEl, {
                    chart: {
                        type: 'line',
                        height: 250,
                        toolbar: { show: false },
                        stacked: false,
                        animations: {
                            enabled: !sv2FiscalChartsBooted,
                            easing: 'easeout',
                            speed: 420
                        },
                        dropShadow: {
                            enabled: false
                        },
                        zoom: {
                            enabled: false
                        }
                    },
                    series: trendSeries,
                    colors: ['#1D4ED8', '#64748B', '#10B981', '#F59E0B'],
                    stroke: {
                        width: [2.2, 1.8, 1.8, 0],
                        curve: 'smooth',
                        lineCap: 'round'
                    },
                    fill: {
                        type: ['gradient', 'solid', 'solid', 'solid'],
                        opacity: [0.10, 0.95, 0.95, 0.82],
                        gradient: {
                            shadeIntensity: 1,
                            opacityFrom: 0.12,
                            opacityTo: 0.015,
                            stops: [0, 100]
                        }
                    },
                    dataLabels: {
                        enabled: false
                    },
                    markers: {
                        size: [0, 2.8, 2.8, 0],
                        strokeWidth: 0,
                        hover: {
                            sizeOffset: 2
                        }
                    },
                    grid: {
                        borderColor: 'rgba(148,163,184,.12)',
                        strokeDashArray: 3,
                        padding: {
                            top: 2,
                            right: 8,
                            bottom: 0,
                            left: 6
                        }
                    },
                    xaxis: {
                        categories: labels,
                        axisBorder: {
                            show: false
                        },
                        axisTicks: {
                            show: false
                        },
                        crosshairs: {
                            show: false
                        },
                        labels: {
                            trim: true,
                            hideOverlappingLabels: true,
                            style: {
                                fontSize: '11px',
                                fontWeight: 700,
                                colors: '#64748b'
                            }
                        }
                    },
                    yaxis: {
                        tickAmount: 4,
                        forceNiceScale: true,
                        labels: {
                            minWidth: 84,
                            style: {
                                fontSize: '11px',
                                fontWeight: 700,
                                colors: ['#64748b']
                            },
                            formatter: function (val) {
                                return moneyFormat(val);
                            }
                        }
                    },
                    legend: {
                        show: false
                    },
                    tooltip: {
                        shared: true,
                        intersect: false,
                        x: {
                            show: true
                        },
                        y: {
                            formatter: function (val) {
                                return moneyFormat(val);
                            }
                        }
                    },
                    plotOptions: {
                        bar: {
                            borderRadius: 4,
                            columnWidth: '22%'
                        }
                    }
                });

                sv2TrendChart.render();
            } else {
                try {
                    sv2TrendChart.updateOptions({
                        chart: {
                            animations: {
                                enabled: false
                            }
                        },
                        xaxis: {
                            categories: labels
                        }
                    }, false, false, false);

                    sv2TrendChart.updateSeries(trendSeries, false);
                } catch (e) {}
            }
        }

        if (fiscalMixChartEl && mix.length) {
            if (!sv2MixChart || forceRedraw) {
                if (sv2MixChart) {
                    try { sv2MixChart.destroy(); } catch (e) {}
                    sv2MixChart = null;
                }

                sv2MixChart = new window.ApexCharts(fiscalMixChartEl, {
                    chart: {
                        type: 'donut',
                        height: 210,
                        animations: {
                            enabled: !sv2FiscalChartsBooted,
                            easing: 'easeout',
                            speed: 420
                        }
                    },
                    series: mix,
                    labels: mixLabels,
                    colors: ['#1D4ED8', '#10B981', '#F59E0B', '#F43F5E'],
                    legend: {
                        position: 'bottom',
                        fontSize: '10px',
                        fontWeight: 700,
                        itemMargin: {
                            horizontal: 8,
                            vertical: 2
                        }
                    },
                    dataLabels: {
                        enabled: true,
                        style: {
                            fontSize: '10px',
                            fontWeight: 700
                        },
                        formatter: function (value) {
                            return value >= 7 ? value.toFixed(1) + '%' : '';
                        }
                    },
                    stroke: {
                        width: 0
                    },
                    plotOptions: {
                        pie: {
                            expandOnClick: false,
                            donut: {
                                size: '72%',
                                labels: {
                                    show: true,
                                    name: {
                                        show: true,
                                        offsetY: -8,
                                        fontSize: '11px',
                                        fontWeight: 700,
                                        color: '#64748b'
                                    },
                                    value: {
                                        show: false
                                    },
                                    total: {
                                        show: true,
                                        showAlways: true,
                                        label: 'Corte fiscal',
                                        fontSize: '11px',
                                        fontWeight: 700,
                                        color: '#64748b',
                                        formatter: function () {
                                            const total = mix.reduce(function (acc, item) {
                                                return acc + Number(item || 0);
                                            }, 0);
                                            return moneyFormat(total);
                                        }
                                    }
                                }
                            }
                        }
                    },
                    tooltip: {
                        y: {
                            formatter: function (val) {
                                return moneyFormat(val);
                            }
                        }
                    },
                    states: {
                        hover: {
                            filter: {
                                type: 'none'
                            }
                        },
                        active: {
                            filter: {
                                type: 'none'
                            }
                        }
                    }
                });

                sv2MixChart.render();
            } else {
                try {
                    sv2MixChart.updateOptions({
                        chart: {
                            animations: {
                                enabled: false
                            }
                        },
                        labels: mixLabels
                    }, false, false, false);

                    sv2MixChart.updateSeries(mix, false);
                } catch (e) {}
            }
        }

        sv2FiscalChartsBooted = true;
    }

        function refreshFiscalChartsSilently(forceRedraw = false) {
        if (sv2FiscalChartsRefreshTimer) {
            clearTimeout(sv2FiscalChartsRefreshTimer);
        }

        sv2FiscalChartsRefreshTimer = setTimeout(function () {
            renderFiscalCharts(forceRedraw);

            try {
                if (sv2TrendChart) {
                    sv2TrendChart.updateOptions({}, false, false, false);
                }
            } catch (e) {}

            try {
                if (sv2MixChart) {
                    sv2MixChart.updateOptions({}, false, false, false);
                }
            } catch (e) {}
        }, 120);
    }

    function submitWithAjax(form) {
        const type = form.getAttribute('data-sv2-upload-form') || 'metadata';

        if (!form.reportValidity()) {
            return;
        }

        const xhr = new XMLHttpRequest();
        const formData = new FormData(form);

        closeAllModals();
        showLoading(type);
        setFormDisabled(form, true);

        xhr.open('POST', form.action, true);
        xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
        xhr.setRequestHeader('Accept', 'application/json');

        xhr.upload.onprogress = function (event) {
            const stage = $('#sv2LoadingStage');
            const hint = $('#sv2LoadingHint');

            if (!stage) return;

            if (event.lengthComputable) {
                const percent = Math.round((event.loaded / event.total) * 100);
                stage.textContent = 'Subiendo archivo (' + percent + '%)';
                if (hint) {
                    hint.textContent = 'Carga en progreso. Espera a que el servidor termine de procesar el archivo.';
                }
                return;
            }

            stage.textContent = 'Subiendo archivo';
        };

        xhr.onreadystatechange = function () {
            if (xhr.readyState !== 4) return;

            hideLoading();
            setFormDisabled(form, false);

            let response = null;

            try {
                response = JSON.parse(xhr.responseText);
            } catch (e) {
                response = null;
            }

            if (xhr.status >= 200 && xhr.status < 300 && response && response.ok) {
                window.location.href = response.redirect_url || window.location.href;
                return;
            }

            showAjaxError(xhr, response);
        };

        xhr.onerror = function () {
            hideLoading();
            setFormDisabled(form, false);
            alert('No se pudo completar la carga. Revisa tu conexión, la ruta o el log del servidor.');
        };

        xhr.send(formData);
    }

    $$('[data-sv2-open]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            openModal(btn.getAttribute('data-sv2-open'));
        });
    });

    $$('[data-sv2-close]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            closeModal(btn.getAttribute('data-sv2-close'));
        });
    });

    document.addEventListener('keydown', function (e) {
        if (e.key !== 'Escape') return;
        if (loading && loading.classList.contains('is-open')) return;
        closeAllModals();
    });

    uploadForms.forEach(function (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();
            submitWithAjax(form);
        });
    });

            const sectionRegistry = [
        {
            key: 'dataLoad',
            button: toggleDataLoadBtn,
            panel: dataLoadBlock,
            mode: 'hidden'
        },
        {
            key: 'metadata',
            button: toggleBtn,
            panel: metadataBlock,
            mode: 'collapsed'
        },
        {
            key: 'xml',
            button: toggleXmlBtn,
            panel: xmlBlock,
            mode: 'collapsed'
        },
        {
            key: 'report',
            button: toggleReportBtn,
            panel: reportBlock,
            mode: 'collapsed'
        },
        {
            key: 'fiscal',
            button: toggleFiscalBtn,
            panel: fiscalBlock,
            mode: 'collapsed'
        },
        {
            key: 'downloads',
            button: toggleDownloadsBtn,
            panel: downloadsBlock,
            mode: 'collapsed'
        }
    ].filter(function (entry) {
        return !!entry.button && !!entry.panel;
    });

    const sectionMap = sectionRegistry.reduce(function (acc, entry) {
        acc[entry.key] = entry;
        return acc;
    }, {});

    function isSectionCollapsed(entry) {
        if (!entry || !entry.panel) return true;

        if (entry.mode === 'hidden') {
            return entry.panel.hasAttribute('hidden');
        }

        return entry.panel.classList.contains('sv2Section--collapsed');
    }

        function getSectionAnchor(entry) {
        if (!entry) return null;

        const explicitAnchors = {
            dataLoad: '#dataLoadSection',
            metadata: '#toggleMetadata',
            xml: '#toggleXml',
            report: '#toggleReport',
            fiscal: '#toggleFiscal',
            downloads: '#toggleDownloads'
        };

        const explicitSelector = explicitAnchors[entry.key] || null;
        if (explicitSelector) {
            const explicitNode = $(explicitSelector);
            if (explicitNode) {
                const explicitSection = explicitNode.closest('.sv2Section');
                if (explicitSection) return explicitSection;
                return explicitNode;
            }
        }

        const panelSection = entry.panel?.closest('.sv2Section');
        if (panelSection) return panelSection;

        const buttonSection = entry.button?.closest('.sv2Section');
        if (buttonSection) return buttonSection;

        const buttonBar = entry.button?.closest('.sv2MetaBar');
        if (buttonBar) return buttonBar;

        return entry.button || entry.panel || null;
    }

    function syncSectionState(entry) {
        if (!entry || !entry.button || !entry.panel) return;

        const collapsed = isSectionCollapsed(entry);
        const icon = $('.sv2MetaBar__icon', entry.button);

        if (icon) {
            icon.textContent = collapsed ? '+' : '−';
        }

        entry.button.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
    }

      function refreshFiscalChartsVisibility(forceRedraw = false) {
        if (!fiscalTrendChartEl && !fiscalMixChartEl) return;

        window.requestAnimationFrame(function () {
            window.requestAnimationFrame(function () {
                refreshFiscalChartsSilently(forceRedraw);
            });
        });
    }

    function setSectionCollapsed(entry, collapsed) {
        if (!entry || !entry.panel) return;

        if (entry.mode === 'hidden') {
            if (collapsed) {
                entry.panel.setAttribute('hidden', 'hidden');
            } else {
                entry.panel.removeAttribute('hidden');
            }
        } else {
            entry.panel.classList.toggle('sv2Section--collapsed', collapsed);
        }

        syncSectionState(entry);

        if (entry.key === 'fiscal' && !collapsed) {
            refreshFiscalChartsVisibility();
        }
    }

    function collapseAllSections() {
        sectionRegistry.forEach(function (entry) {
            setSectionCollapsed(entry, true);
        });
    }

    function expandAllSections() {
        sectionRegistry.forEach(function (entry) {
            setSectionCollapsed(entry, false);
        });

        refreshFiscalChartsVisibility();
    }

    function expandSectionByKey(key) {
        const entry = sectionMap[key];
        if (!entry) return;
        setSectionCollapsed(entry, false);
    }

    function getDockHeight() {
        if (!sectionDock) return 0;
        return Math.ceil(sectionDock.offsetHeight || sectionDock.getBoundingClientRect().height || 0);
    }

    function updateDockOffsetVar() {
        const offset = getDockHeight() + 96;
        document.documentElement.style.setProperty('--sv2-dock-offset', offset + 'px');
    }

    function isDockPinned() {
        return true;
    }

    function syncDockPinnedState() {
        document.body.classList.add('sv2DockPinned');
        updateDockOffsetVar();
        return true;
    }

    function scrollToEntry(entry) {
        const anchor = getSectionAnchor(entry);
        if (!anchor) return;

        updateDockOffsetVar();

        const dockOffset = getDockHeight() + 28;

        anchor.scrollIntoView({
            behavior: 'smooth',
            block: 'start',
            inline: 'nearest'
        });

        window.setTimeout(function () {
            const finalTop = window.scrollY - dockOffset;
            window.scrollTo({
                top: Math.max(finalTop, 0),
                behavior: 'smooth'
            });
        }, 180);
    }

    function setActiveDockLink(key) {
        sectionDockLinks.forEach(function (link) {
            link.classList.toggle('is-active', link.getAttribute('data-sv2-jump') === key);
        });
    }

    function resolveVisibleSection() {
        let bestKey = 'dataLoad';
        let bestDistance = Number.POSITIVE_INFINITY;
        const dockCompensation = (document.body.classList.contains('sv2DockPinned') ? getDockHeight() : 0) + 72;

        sectionRegistry.forEach(function (entry) {
            const anchor = getSectionAnchor(entry);
            if (!anchor) return;

            const rect = anchor.getBoundingClientRect();
            const distance = Math.abs(rect.top - dockCompensation);

            if (rect.bottom > dockCompensation && distance < bestDistance) {
                bestDistance = distance;
                bestKey = entry.key;
            }
        });

        setActiveDockLink(bestKey);
    }

    function handleViewportSync() {
        syncDockPinnedState();
        resolveVisibleSection();
    }

    sectionRegistry.forEach(function (entry) {
        syncSectionState(entry);

        entry.button.addEventListener('click', function () {
            const collapsed = isSectionCollapsed(entry);
            setSectionCollapsed(entry, !collapsed);
            handleViewportSync();
        });
    });

    collapseAllSections();
    handleViewportSync();
    setActiveDockLink('dataLoad');

    sectionDockLinks.forEach(function (link) {
        link.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();

            const key = link.getAttribute('data-sv2-jump');
            const entry = sectionMap[key];
            if (!entry) return;

            expandSectionByKey(key);
            syncSectionState(entry);
            setActiveDockLink(key);

            if (key === 'fiscal') {
                window.setTimeout(function () {
                    try {
                        refreshFiscalChartsSilently(false);
                    } catch (err) {}
                }, 120);
            }

            window.setTimeout(function () {
                scrollToEntry(entry);
            }, 120);
        });
    });

    if (expandAllBtn) {
        expandAllBtn.addEventListener('click', function () {
            expandAllSections();
            handleViewportSync();
        });
    }

    if (collapseAllBtn) {
        collapseAllBtn.addEventListener('click', function () {
            collapseAllSections();
            handleViewportSync();
        });
    }

    let sv2DockScrollRaf = null;
    let sv2DockResizeRaf = null;

    window.addEventListener('resize', function () {
        if (sv2DockResizeRaf) {
            cancelAnimationFrame(sv2DockResizeRaf);
        }

        sv2DockResizeRaf = requestAnimationFrame(function () {
            handleViewportSync();
            refreshFiscalChartsVisibility();
            sv2DockResizeRaf = null;
        });
    });

    window.addEventListener('scroll', function () {
        if (sv2DockScrollRaf) return;

        sv2DockScrollRaf = requestAnimationFrame(function () {
            handleViewportSync();
            sv2DockScrollRaf = null;
        });
    }, { passive: true });

    const sv2HasErrors = window.sv2Config?.hasErrors || false;
    const sv2OpenModalOnLoad = window.sv2Config?.openModalOnLoad || 'metadataModal';

    if (sv2HasErrors) {
        openModal(sv2OpenModalOnLoad || 'metadataModal');
    }

    document.querySelectorAll('.sv2RfcTable tbody tr').forEach(function (row) {
        const input = row.querySelector('.sv2RfcTableForm input[name="razon_social"]');
        const mirror = row.querySelector('.sv2RfcSaveMirrorBtn')?.closest('form')?.querySelector('input[name="razon_social"]');

        if (!input || !mirror) return;

        const sync = function () {
            mirror.value = input.value;
        };

        input.addEventListener('input', sync);
        input.addEventListener('change', sync);
        sync();
    });

    const chooser = document.getElementById('sv2RfcChooser');
    const control = document.getElementById('sv2RfcChooserControl');
    const menu = document.getElementById('sv2RfcChooserMenu');
    const search = document.getElementById('sv2RfcChooserSearch');
    const list = document.getElementById('sv2RfcChooserList');
    const value = document.getElementById('sv2RfcChooserValue');
    const hidden = document.getElementById('sv2RfcHiddenInput');

    if (chooser && control && menu && search && list && value && hidden) {
        const options = Array.from(list.querySelectorAll('.sv2RfcOption'));

        const openMenu = function () {
            chooser.classList.add('is-open');
            menu.hidden = false;
            control.setAttribute('aria-expanded', 'true');
            setTimeout(function () {
                search.focus();
                search.select();
            }, 20);
        };

        const closeMenu = function () {
            chooser.classList.remove('is-open');
            menu.hidden = true;
            control.setAttribute('aria-expanded', 'false');
        };

        const normalize = function (text) {
            return (text || '')
                .toString()
                .toLowerCase()
                .normalize('NFD')
                .replace(/[\u0300-\u036f]/g, '');
        };

        const filterOptions = function () {
            const term = normalize(search.value);

            options.forEach(function (option) {
                const haystack = normalize(option.getAttribute('data-search') || '');
                const show = term === '' || haystack.includes(term);
                option.hidden = !show;
            });
        };

        const setSelected = function (option) {
            const selectedRfc = option.getAttribute('data-rfc') || '';
            const selectedName = option.getAttribute('data-name') || selectedRfc || 'Selecciona un RFC de trabajo';

            hidden.value = selectedRfc;
            value.textContent = selectedName;

            options.forEach(function (item) {
                item.classList.remove('is-active');
                item.setAttribute('aria-selected', 'false');

                const badge = item.querySelector('.sv2RfcOption__badge');
                if (badge) badge.remove();
            });

            option.classList.add('is-active');
            option.setAttribute('aria-selected', 'true');

            if (!option.querySelector('.sv2RfcOption__badge')) {
                const badge = document.createElement('span');
                badge.className = 'sv2RfcOption__badge';
                badge.textContent = 'Activo';
                option.appendChild(badge);
            }

            closeMenu();
        };

        control.addEventListener('click', function () {
            if (chooser.classList.contains('is-open')) {
                closeMenu();
            } else {
                openMenu();
            }
        });

        search.addEventListener('input', filterOptions);

        options.forEach(function (option) {
            option.addEventListener('click', function () {
                setSelected(option);
            });
        });

        document.addEventListener('click', function (event) {
            if (!chooser.contains(event.target)) {
                closeMenu();
            }
        });

        document.addEventListener('keydown', function (event) {
            if (event.key === 'Escape') {
                closeMenu();
            }
        });
    }

       document.querySelectorAll('[data-sv2-reprocess-smart]').forEach(function (btn) {
        btn.addEventListener('click', async function () {
            if (reprocessAdvancedForm) {
                const rfcInput = reprocessAdvancedForm.querySelector('input[name="rfc_owner"]');
                const scopeSmart = reprocessAdvancedForm.querySelector('input[name="scope"][value="smart"]');
                if (rfcInput && btn.dataset.rfc) rfcInput.value = btn.dataset.rfc;
                if (scopeSmart) scopeSmart.checked = true;
            }

            openModal('fiscalReprocessModal');
            await handleReprocessPreview();
        });
    });

    if (reprocessAdvancedForm) {
        const previewBtn = reprocessAdvancedForm.querySelector('[data-sv2-preview-reprocess]');
        const runBtn = reprocessAdvancedForm.querySelector('[data-sv2-run-reprocess]');

        if (previewBtn) {
            previewBtn.addEventListener('click', async function () {
                await handleReprocessPreview();
            });
        }

        if (runBtn) {
            runBtn.addEventListener('click', async function () {
                const ok = window.confirm('Se ejecutará la relectura del bloque calculado o seleccionado. ¿Deseas continuar?');
                if (!ok) return;
                await handleReprocessRun(false);
            });
        }
    }

    handleViewportSync();
    refreshFiscalChartsVisibility(true);
});