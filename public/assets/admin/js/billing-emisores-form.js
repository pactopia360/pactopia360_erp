(function () {
    'use strict';

    const root = document;
    const page = root.querySelector('.billing-emisores-create-page, .billing-emisores-edit-page');

    if (!page) return;

    /* =========================
     * TABS
     * ========================= */
    const tabs = Array.from(root.querySelectorAll('.bef-tab'));
    const panels = Array.from(root.querySelectorAll('.bef-panel'));

    function activateTab(tabId) {
        tabs.forEach(t => t.classList.remove('active'));
        panels.forEach(p => p.classList.remove('active'));

        const tab = root.querySelector(`.bef-tab[data-tab="${tabId}"]`);
        const panel = root.querySelector(`.bef-panel[data-panel="${tabId}"]`);

        if (tab) tab.classList.add('active');
        if (panel) panel.classList.add('active');
    }

    tabs.forEach(tab => {
        tab.addEventListener('click', () => {
            activateTab(tab.dataset.tab);
        });
    });

    /* =========================
     * JSON HELPERS
     * ========================= */
    function safeJsonParse(value) {
        try {
            return JSON.parse(value || '{}');
        } catch {
            return {};
        }
    }

    function updateJsonField(source, targetId) {
        const target = root.getElementById(targetId);
        if (!target) return;

        try {
            target.value = JSON.stringify(source, null, 2);
        } catch {
            target.value = '{}';
        }
    }

    /* =========================
     * DIRECCIÓN
     * ========================= */
    const direccionFields = [
        'calle', 'numero_exterior', 'numero_interior',
        'colonia', 'municipio', 'estado', 'cp', 'pais'
    ];

    const direccionInputs = {};
    direccionFields.forEach(f => {
        direccionInputs[f] = root.getElementById(`direccion_${f}`);
    });

    const direccionJson = root.getElementById('direccion_json');

    function syncDireccion() {
        const data = {};
        direccionFields.forEach(f => {
            if (direccionInputs[f]) {
                data[f] = direccionInputs[f].value || '';
            }
        });
        updateJsonField(data, 'direccion_json');
    }

    direccionFields.forEach(f => {
        if (direccionInputs[f]) {
            direccionInputs[f].addEventListener('input', syncDireccion);
        }
    });

    /* =========================
     * SERIES
     * ========================= */
    const seriesInput = root.getElementById('series_input');
    const seriesJson = root.getElementById('series_json');

    function syncSeries() {
        if (!seriesInput) return;

        const raw = seriesInput.value.split(',');
        const clean = raw.map(s => s.trim()).filter(s => s !== '');

        updateJsonField(clean, 'series_json');
    }

    if (seriesInput) {
        seriesInput.addEventListener('input', syncSeries);
    }

    /* =========================
     * CERTIFICADOS
     * ========================= */
    const certPass = root.getElementById('csd_password');
    const certJson = root.getElementById('certificados_json');

    function syncCertificados() {
        const data = {
            password: certPass?.value || ''
        };
        updateJsonField(data, 'certificados_json');
    }

    if (certPass) {
        certPass.addEventListener('input', syncCertificados);
    }

    /* =========================
     * IA VALIDACIÓN (simulada)
     * ========================= */
    const aiButton = root.getElementById('bef-ai-validate');

    function validateRFC(rfc) {
        return /^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/.test(rfc);
    }

    function runAIValidation() {
        const rfc = root.getElementById('rfc')?.value || '';
        const regimen = root.getElementById('regimen_fiscal')?.value || '';
        const cp = root.getElementById('direccion_cp')?.value || '';

        let issues = [];

        if (!validateRFC(rfc)) {
            issues.push('RFC inválido');
        }

        if (!regimen) {
            issues.push('Falta régimen fiscal');
        }

        if (!cp || cp.length < 5) {
            issues.push('CP fiscal incompleto');
        }

        if (issues.length === 0) {
            alert('✔ Emisor validado correctamente');
        } else {
            alert('⚠ Problemas detectados:\n- ' + issues.join('\n- '));
        }
    }

    if (aiButton) {
        aiButton.addEventListener('click', runAIValidation);
    }

    /* =========================
     * INIT
     * ========================= */
    activateTab('general');
    syncDireccion();
    syncSeries();
    syncCertificados();

})();