(function () {
    'use strict';

    function bootTimbresHits() {
        const shell = document.querySelector('.tim-shell');

        if (!shell || shell.dataset.timReady === '1') {
            return;
        }

        shell.dataset.timReady = '1';

        function parseJson(value, fallback) {
            try {
                return JSON.parse(value || '');
            } catch (error) {
                return fallback;
            }
        }

        function money(value) {
            return Number(value || 0).toLocaleString('es-MX', {
                style: 'currency',
                currency: 'MXN',
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            });
        }

        function compactNumber(value) {
            return Number(value || 0).toLocaleString('es-MX');
        }

        function maskSecret(value) {
            const text = String(value || '').trim();

            if (!text) return 'No configurada';
            if (text.length <= 12) return '•'.repeat(Math.max(6, text.length));

            return text.substring(0, 6) + '••••••••••••••••••' + text.substring(text.length - 6);
        }

        document.querySelectorAll('.tim-section__header').forEach((header) => {
            header.addEventListener('click', function () {
                const section = this.closest('.tim-section');
                if (section) section.classList.toggle('is-open');
            });
        });

        const compactButton = document.getElementById('timCompactToggle');
        if (compactButton) {
            compactButton.addEventListener('click', function () {
                shell.classList.toggle('is-compact');

                const label = this.querySelector('span:last-child');
                if (label) {
                    label.textContent = shell.classList.contains('is-compact') ? 'Normal' : 'Compacto';
                }
            });
        }

        document.querySelectorAll('.tim-env-btn').forEach((button) => {
            button.addEventListener('click', function () {
                const target = this.dataset.envTarget || 'sandbox';

                document.querySelectorAll('.tim-env-btn').forEach((btn) => btn.classList.remove('is-active'));
                this.classList.add('is-active');

                shell.dataset.timEnv = target;

                const label = document.getElementById('timEnvLabel');
                const text = document.getElementById('timEnvText');

                if (label) label.textContent = target === 'production' ? 'Producción' : 'Pruebas';

                if (text) {
                    text.textContent = target === 'production'
                        ? 'Los CFDI timbrados descuentan timbres reales.'
                        : 'Modo seguro para pruebas, validación y configuración PAC.';
                }

                const metricEnv = document.querySelector('.tim-metric--env');
                if (metricEnv) {
                    const strong = metricEnv.querySelector('strong');
                    const em = metricEnv.querySelector('em');

                    if (strong) strong.textContent = target === 'production' ? 'Producción' : 'Pruebas';
                    if (em) em.textContent = target === 'production' ? 'Consume saldo real' : 'Sin consumo real';
                }
            });
        });

        document.querySelectorAll('.tim-secret-toggle').forEach((button) => {
            button.addEventListener('click', function () {
                const code = this.closest('.tim-key-box')?.querySelector('code[data-secret]');
                if (!code) return;

                const secret = code.dataset.secret || '';
                const visible = code.dataset.visible === '1';

                code.textContent = visible ? maskSecret(secret) : (secret || 'No configurada');
                code.dataset.visible = visible ? '0' : '1';
                this.textContent = visible ? 'Ver' : 'Ocultar';
            });
        });

        function getRows() {
            const table = document.getElementById('timCfdiTable');
            if (!table) return [];

            return Array.from(table.querySelectorAll('tbody tr')).filter((row) => row.querySelectorAll('td').length > 1);
        }

        function applyFilters() {
            const q = (document.getElementById('timTableSearch')?.value || '').trim().toLowerCase();
            const status = (document.getElementById('timStatusFilter')?.value || '').trim().toLowerCase();
            const tipo = (document.getElementById('timTipoFilter')?.value || '').trim().toLowerCase();
            const env = (document.getElementById('timEnvFilter')?.value || '').trim().toLowerCase();

            getRows().forEach((row) => {
                const text = row.textContent.toLowerCase();
                const rowStatus = String(row.dataset.status || '').toLowerCase();
                const rowTipo = String(row.dataset.tipo || '').toLowerCase();
                const rowEnv = String(row.dataset.env || '').toLowerCase();

                row.style.display =
                    (q === '' || text.includes(q)) &&
                    (status === '' || rowStatus === status) &&
                    (tipo === '' || rowTipo === tipo) &&
                    (env === '' || rowEnv === env)
                        ? ''
                        : 'none';
            });
        }

        ['timTableSearch', 'timStatusFilter', 'timTipoFilter', 'timEnvFilter'].forEach((id) => {
            const el = document.getElementById(id);
            if (!el) return;

            el.addEventListener('input', applyFilters);
            el.addEventListener('change', applyFilters);
        });

        const clearButton = document.getElementById('timClearFilters');
        if (clearButton) {
            clearButton.addEventListener('click', function () {
                ['timTableSearch', 'timStatusFilter', 'timTipoFilter', 'timEnvFilter'].forEach((id) => {
                    const el = document.getElementById(id);
                    if (el) el.value = '';
                });

                applyFilters();
            });
        }

        function tableToCsv(table) {
            return Array.from(table.querySelectorAll('tr'))
                .filter((row) => row.style.display !== 'none')
                .map((row) => {
                    return Array.from(row.querySelectorAll('th,td'))
                        .map((cell) => {
                            const text = (cell.textContent || '').replace(/\s+/g, ' ').trim().replace(/"/g, '""');
                            return `"${text}"`;
                        })
                        .join(',');
                })
                .join('\n');
        }

        function rfcToCsv() {
            const header = '"RFC","Receptor","Cantidad","Monto"';

            const body = Array.from(document.querySelectorAll('.tim-rfc-row')).map((row) => {
                const left = row.querySelector('div:first-child');
                const right = row.querySelector('div:last-child');

                const data = [
                    left?.querySelector('strong')?.textContent || '',
                    left?.querySelector('span')?.textContent || '',
                    right?.querySelector('strong')?.textContent || '',
                    right?.querySelector('span')?.textContent || ''
                ];

                return data.map((text) => `"${String(text).replace(/\s+/g, ' ').trim().replace(/"/g, '""')}"`).join(',');
            });

            return [header].concat(body).join('\n');
        }

        function downloadCsv(filename, csv) {
            const blob = new Blob(['\uFEFF' + csv], { type: 'text/csv;charset=utf-8;' });
            const url = URL.createObjectURL(blob);
            const link = document.createElement('a');

            link.href = url;
            link.download = filename;
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            URL.revokeObjectURL(url);
        }

        document.querySelectorAll('[data-tim-export]').forEach((button) => {
            button.addEventListener('click', function (event) {
                event.preventDefault();

                const type = this.dataset.timExport;
                const today = new Date().toISOString().slice(0, 10);

                if (type === 'rfc') {
                    downloadCsv('timbres_consumo_rfc_' + today + '.csv', rfcToCsv());
                    return;
                }

                const table = document.getElementById('timCfdiTable');
                if (!table) return;

                downloadCsv('timbres_cfdi_' + today + '.csv', tableToCsv(table));
            });
        });

        const canvas = document.getElementById('timConsumoChart');

        if (canvas && typeof Chart !== 'undefined') {
            const raw = parseJson(shell.dataset.timSeries, {
                labels: [],
                consumo: [],
                monto: []
            });

            const labels = Array.isArray(raw.labels) && raw.labels.length ? raw.labels : ['Sin datos'];
            const consumo = Array.isArray(raw.consumo) && raw.consumo.length ? raw.consumo : [0];
            const monto = Array.isArray(raw.monto) && raw.monto.length ? raw.monto : [0];

            new Chart(canvas.getContext('2d'), {
                type: 'bar',
                data: {
                    labels: labels,
                    datasets: [
                        {
                            type: 'bar',
                            label: 'Timbres',
                            data: consumo,
                            borderWidth: 1,
                            borderRadius: 10,
                            yAxisID: 'y'
                        },
                        {
                            type: 'line',
                            label: 'Monto',
                            data: monto,
                            tension: 0.35,
                            borderWidth: 2,
                            pointRadius: 3,
                            yAxisID: 'y1'
                        }
                    ]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    interaction: {
                        mode: 'index',
                        intersect: false
                    },
                    plugins: {
                        legend: {
                            labels: {
                                usePointStyle: true,
                                boxWidth: 8,
                                font: {
                                    size: 11,
                                    weight: 'bold'
                                }
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function (context) {
                                    return context.dataset.yAxisID === 'y1'
                                        ? context.dataset.label + ': ' + money(context.raw)
                                        : context.dataset.label + ': ' + compactNumber(context.raw);
                                }
                            }
                        }
                    },
                    scales: {
                        x: {
                            grid: { display: false }
                        },
                        y: {
                            beginAtZero: true,
                            ticks: { precision: 0 },
                            grid: { color: 'rgba(148, 163, 184, .18)' }
                        },
                        y1: {
                            beginAtZero: true,
                            position: 'right',
                            grid: { drawOnChartArea: false },
                            ticks: {
                                callback: function (value) {
                                    return money(value);
                                }
                            }
                        }
                    }
                }
            });
        }

        const facturotopiaTestButton = document.getElementById('timFacturotopiaTest');
        const facturotopiaResult = document.getElementById('timFacturotopiaResult');

        if (facturotopiaTestButton && facturotopiaResult) {
            facturotopiaTestButton.addEventListener('click', async function () {
                const url = this.dataset.url || '';
                const csrf = this.dataset.csrf || '';
                const env = shell.dataset.timEnv || 'sandbox';

                if (!url) {
                    return;
                }

                this.disabled = true;
                this.classList.add('is-loading');
                facturotopiaResult.hidden = false;
                facturotopiaResult.className = 'tim-connection-result is-info';
                facturotopiaResult.textContent = 'Probando conexión con Facturotopia...';

                try {
                    const response = await fetch(url, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': csrf
                        },
                        body: JSON.stringify({
                            env: env
                        })
                    });

                    const json = await response.json();

                    facturotopiaResult.className = json.ok
                        ? 'tim-connection-result is-success'
                        : 'tim-connection-result is-danger';

                    facturotopiaResult.innerHTML = `
                        <strong>${json.message || 'Resultado de conexión'}</strong>
                        <span>Ambiente: ${json.env || env}</span>
                        <span>Base URL: ${json.base_url || 'No configurada'}</span>
                        <span>HTTP: ${json.status || 'N/D'} · Tiempo: ${json.response_ms || 0} ms</span>
                    `;
                } catch (error) {
                    facturotopiaResult.className = 'tim-connection-result is-danger';
                    facturotopiaResult.textContent = 'Error al probar conexión: ' + error.message;
                } finally {
                    this.disabled = false;
                    this.classList.remove('is-loading');
                }
            });
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', bootTimbresHits);
    } else {
        bootTimbresHits();
    }
})();