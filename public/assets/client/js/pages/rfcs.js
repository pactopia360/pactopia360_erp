(function () {
  'use strict';

  const qs = (selector, root = document) => root.querySelector(selector);
  const qsa = (selector, root = document) => Array.from(root.querySelectorAll(selector));

  const ENDPOINTS = {
    states: '/cliente/facturacion/locations/states',
    municipalities: '/cliente/facturacion/locations/municipalities',
    colonies: '/cliente/facturacion/locations/colonies',
    postalCode: '/cliente/facturacion/postal-code/'
  };

  const modals = {
    create: qs('#rfcCreateModal'),
    edit: qs('#rfcEditModal')
  };

  const stateCache = {
    states: null,
    municipalities: {},
    colonies: {},
    cp: {}
  };

  const escapeHtml = (value) => {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  };

  const normalizeList = (payload) => {
    if (Array.isArray(payload)) return payload;
    if (Array.isArray(payload?.data)) return payload.data;
    if (Array.isArray(payload?.items)) return payload.items;
    if (Array.isArray(payload?.states)) return payload.states;
    if (Array.isArray(payload?.municipalities)) return payload.municipalities;
    if (Array.isArray(payload?.colonies)) return payload.colonies;
    if (Array.isArray(payload?.colonias)) return payload.colonias;
    return [];
  };

  const itemValue = (item) => {
    if (typeof item === 'string') return item;
    return item.value || item.nombre || item.name || item.estado || item.municipio || item.colonia || item.asentamiento || item.label || '';
  };

  const itemLabel = (item) => {
    if (typeof item === 'string') return item;
    return item.label || item.nombre || item.name || item.estado || item.municipio || item.colonia || item.asentamiento || item.value || '';
  };

  const setSelectOptions = (select, items, placeholder, selectedValue = '') => {
    if (!select) return;

    const selected = String(selectedValue || select.value || '');
    select.innerHTML = `<option value="">${placeholder}</option>`;

    items.forEach((item) => {
      const value = itemValue(item);
      const label = itemLabel(item);
      if (!value && !label) return;

      const option = document.createElement('option');
      option.value = value || label;
      option.textContent = label || value;
      select.appendChild(option);
    });

    if (selected) {
      select.value = selected;

      if (select.value !== selected) {
        const option = document.createElement('option');
        option.value = selected;
        option.textContent = selected;
        option.selected = true;
        select.appendChild(option);
      }
    }
  };

  const fetchJson = async (url) => {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    return response.json();
  };

  const loadStates = async (modal, selectedValue = '') => {
    const stateSelect = qs('[data-location-state]', modal);
    if (!stateSelect) return;

    try {
      if (!stateCache.states) {
        stateCache.states = normalizeList(await fetchJson(ENDPOINTS.states));
      }

      setSelectOptions(stateSelect, stateCache.states, 'Selecciona estado', selectedValue);
    } catch (error) {
      console.warn('No se pudieron cargar estados.', error);
      setSelectOptions(stateSelect, [], 'Selecciona estado', selectedValue);
    }
  };

  const loadMunicipalities = async (modal, stateValue = '', selectedValue = '') => {
    const municipalitySelect = qs('[data-location-municipality]', modal);
    if (!municipalitySelect) return;

    const state = stateValue || qs('[data-location-state]', modal)?.value || '';

    if (!state) {
      setSelectOptions(municipalitySelect, [], 'Selecciona municipio', selectedValue);
      return;
    }

    try {
      if (!stateCache.municipalities[state]) {
        const url = `${ENDPOINTS.municipalities}?estado=${encodeURIComponent(state)}&state=${encodeURIComponent(state)}`;
        stateCache.municipalities[state] = normalizeList(await fetchJson(url));
      }

      setSelectOptions(municipalitySelect, stateCache.municipalities[state], 'Selecciona municipio', selectedValue);
    } catch (error) {
      console.warn('No se pudieron cargar municipios.', error);
      setSelectOptions(municipalitySelect, [], 'Selecciona municipio', selectedValue);
    }
  };

  const loadColonies = async (modal, cpValue = '', selectedValue = '') => {
    const colonySelect = qs('[data-location-colony]', modal);
    if (!colonySelect) return;

    const cp = (cpValue || qs('[data-cp-autofill]', modal)?.value || '').replace(/\D/g, '').slice(0, 5);
    const state = qs('[data-location-state]', modal)?.value || '';
    const municipality = qs('[data-location-municipality]', modal)?.value || '';
    const cacheKey = `${cp}|${state}|${municipality}`;

    if (!cp && !state && !municipality) {
      setSelectOptions(colonySelect, [], 'Selecciona colonia', selectedValue);
      return;
    }

    try {
      if (!stateCache.colonies[cacheKey]) {
        const url = `${ENDPOINTS.colonies}?cp=${encodeURIComponent(cp)}&codigo_postal=${encodeURIComponent(cp)}&estado=${encodeURIComponent(state)}&municipio=${encodeURIComponent(municipality)}`;
        stateCache.colonies[cacheKey] = normalizeList(await fetchJson(url));
      }

      setSelectOptions(colonySelect, stateCache.colonies[cacheKey], 'Selecciona colonia', selectedValue);
    } catch (error) {
      console.warn('No se pudieron cargar colonias.', error);
      setSelectOptions(colonySelect, [], 'Selecciona colonia', selectedValue);
    }
  };

  const fillAddressByCp = async (cpInput) => {
    if (!cpInput) return;

    const modal = cpInput.closest('.rfcs-modal');
    if (!modal) return;

    const cp = cpInput.value.replace(/\D/g, '').slice(0, 5);
    cpInput.value = cp;

    if (cp.length !== 5) return;

    try {
      if (!stateCache.cp[cp]) {
        stateCache.cp[cp] = await fetchJson(`${ENDPOINTS.postalCode}${cp}`);
      }

      const payload = stateCache.cp[cp];
      const data = payload.data || payload;

      const estado = data.estado || data.state || data.estado_nombre || '';
      const municipio = data.municipio || data.municipality || data.municipio_nombre || '';
      const colonias = data.colonias || data.colonies || payload.colonias || payload.colonies || [];

      await loadStates(modal, estado);

      if (estado) {
        await loadMunicipalities(modal, estado, municipio);
      }

      const colonySelect = qs('[data-location-colony]', modal);
      if (colonySelect) {
        setSelectOptions(colonySelect, normalizeList(colonias), 'Selecciona colonia', colonySelect.value);
      }

      if (municipio && qs('[data-location-municipality]', modal)) {
        qs('[data-location-municipality]', modal).value = municipio;
      }

      refreshAi(modal);
    } catch (error) {
      console.warn('No se pudo autollenar dirección por CP.', error);
    }
  };

  const getMeta = (data) => data?.meta && typeof data.meta === 'object' ? data.meta : {};
  const getConfig = (data) => getMeta(data).config_fiscal || {};
  const getAddress = (data) => getMeta(data).direccion || data?.direccion || {};
  const getBranding = (data) => getMeta(data).branding || {};
  const getEmailConfig = (data) => getMeta(data).email || {};
  const getComplements = (data) => Array.isArray(getMeta(data).complementos) ? getMeta(data).complementos : [];

  const setValue = (id, value) => {
    const input = qs(`#${id}`);
    if (input) input.value = value || '';
  };

  const setChecked = (id, value) => {
    const input = qs(`#${id}`);
    if (input) input.checked = !!value;
  };

  const parsePayload = (button) => {
    try {
      return JSON.parse(button.getAttribute('data-rfc') || '{}');
    } catch (error) {
      console.error('No se pudo leer el RFC seleccionado.', error);
      return {};
    }
  };

  const openModal = async (modal) => {
    if (!modal) return;

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    document.body.classList.add('rfcs-modal-open');

    injectAiPanel(modal);
    await loadStates(modal);
    refreshAi(modal);
  };

  const closeModals = () => {
    Object.values(modals).forEach((modal) => {
      if (!modal) return;
      modal.classList.remove('is-open');
      modal.setAttribute('aria-hidden', 'true');
    });

    document.body.classList.remove('rfcs-modal-open');
  };

  const injectAiPanel = (modal) => {
    if (!modal || qs('.rfcs-ai-panel', modal)) return;

    const tabs = qs('.rfcs-tabs', modal);
    if (!tabs) return;

    const panel = document.createElement('div');
    panel.className = 'rfcs-ai-panel';
    panel.innerHTML = `
      <div class="rfcs-ai-icon">IA</div>
      <div class="rfcs-ai-copy">
        <strong>Asistente fiscal</strong>
        <span data-ai-summary>Analizando datos fiscales del emisor...</span>
      </div>
      <div class="rfcs-ai-score">
        <b data-ai-score>0</b>
        <small>/100</small>
      </div>
      <button type="button" class="rfcs-ai-fill" data-ai-autofill>Completar sugeridos</button>
    `;

    tabs.insertAdjacentElement('afterend', panel);
  };

  const validateRfc = (rfc) => {
    const value = String(rfc || '').trim().toUpperCase();

    return {
      value,
      isValid: /^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/.test(value),
      type: value.length === 12 ? 'Moral' : value.length === 13 ? 'Física' : 'Pendiente'
    };
  };

  const getModalData = (modal) => {
    return {
      rfc: qs('#edit_rfc', modal)?.value || qs('input[name="rfc"]', modal)?.value || '',
      razon: qs('#edit_razon_social', modal)?.value || qs('input[name="razon_social"]', modal)?.value || '',
      regimen: qs('#edit_regimen_fiscal', modal)?.value || qs('select[name="regimen_fiscal"]', modal)?.value || '',
      cp: qs('#edit_codigo_postal', modal)?.value || qs('input[name="codigo_postal"]', modal)?.value || '',
      estado: qs('[data-location-state]', modal)?.value || '',
      municipio: qs('[data-location-municipality]', modal)?.value || '',
      colonia: qs('[data-location-colony]', modal)?.value || '',
      email: qs('#edit_email', modal)?.value || qs('input[name="email"]', modal)?.value || '',
      comercial: qs('#edit_nombre_comercial', modal)?.value || qs('input[name="nombre_comercial"]', modal)?.value || '',
      web: qs('#edit_sitio_web', modal)?.value || qs('input[name="sitio_web"]', modal)?.value || '',
      correoFacturacion: qs('#edit_correo_facturacion', modal)?.value || qs('input[name="correo_facturacion"]', modal)?.value || '',
      color: qs('#edit_color_pdf', modal)?.value || qs('input[name="color_pdf"]', modal)?.value || '',
      plantilla: qs('#edit_plantilla_pdf', modal)?.value || qs('select[name="plantilla_pdf"]', modal)?.value || '',
      hasFiel: modal.dataset.hasFiel === '1',
      hasCsd: modal.dataset.hasCsd === '1',
      hasSeries: modal.dataset.hasSeries === '1',
      hasLogo: modal.dataset.hasLogo === '1'
    };
  };

  const refreshAi = (modal) => {
    if (!modal) return;

    const data = getModalData(modal);
    const rfcCheck = validateRfc(data.rfc);
    let score = 0;
    const issues = [];

    if (rfcCheck.isValid) score += 12; else issues.push('RFC inválido');
    if (data.razon) score += 10; else issues.push('razón social');
    if (data.regimen) score += 13; else issues.push('régimen fiscal');
    if (data.cp && data.cp.length === 5) score += 10; else issues.push('CP fiscal');
    if (data.estado && data.municipio && data.colonia) score += 10; else issues.push('domicilio fiscal');
    if (data.hasFiel) score += 10; else issues.push('FIEL');
    if (data.hasCsd) score += 18; else issues.push('CSD');
    if (data.hasSeries) score += 10; else issues.push('serie/folio');
    if (data.email || data.correoFacturacion) score += 5; else issues.push('correo');
    if (data.hasLogo) score += 4;
    if (data.comercial) score += 4;
    if (data.plantilla) score += 4;

    const scoreEl = qs('[data-ai-score]', modal);
    const summary = qs('[data-ai-summary]', modal);

    if (scoreEl) scoreEl.textContent = String(Math.min(score, 100));

    if (summary) {
      if (score >= 85) {
        summary.textContent = `Perfil listo para CFDI. RFC ${rfcCheck.type}.`;
      } else {
        summary.textContent = `Faltan: ${issues.slice(0, 5).join(', ')}.`;
      }
    }
  };

  const setCertStatus = (data) => {
    const meta = getMeta(data);

    const hasFiel = !!(
      data.fiel_cer_path ||
      data.fiel_key_path ||
      data.certificados?.fiel?.cer_path ||
      data.certificados?.fiel?.key_path ||
      meta.fiel
    );

    const hasCsd = !!(
      data.csd_cer_path ||
      data.csd_key_path ||
      data.certificados?.csd?.cer_path ||
      data.certificados?.csd?.key_path ||
      meta.csd
    );

    const hasSeries = Array.isArray(meta.series) && meta.series.length > 0;
    const hasLogo = !!getBranding(data).logo_path;

    if (modals.edit) {
      modals.edit.dataset.hasFiel = hasFiel ? '1' : '0';
      modals.edit.dataset.hasCsd = hasCsd ? '1' : '0';
      modals.edit.dataset.hasSeries = hasSeries ? '1' : '0';
      modals.edit.dataset.hasLogo = hasLogo ? '1' : '0';
    }

    const fielBox = qs('#edit_fiel_status_box');
    const csdBox = qs('#edit_csd_status_box');

    if (fielBox) fielBox.className = `rfcs-cert-status ${hasFiel ? 'ok' : 'pending'}`;
    if (csdBox) csdBox.className = `rfcs-cert-status ${hasCsd ? 'ok' : 'pending'}`;

    setText('#edit_fiel_status', hasFiel ? 'FIEL cargada' : 'FIEL pendiente');
    setText('#edit_fiel_detail', hasFiel ? 'Archivos detectados en el RFC.' : 'Carga .cer, .key y contraseña.');

    setText('#edit_csd_status', hasCsd ? 'CSD listo' : 'CSD pendiente');
    setText('#edit_csd_detail', hasCsd ? 'Disponible para timbrado CFDI.' : 'Necesario para timbrar CFDI.');

    setText('#audit_fiel_status', hasFiel ? 'FIEL cargada' : 'FIEL pendiente');
    setText('#audit_csd_status', hasCsd ? 'CSD configurado' : 'CSD pendiente');
    setText('#audit_series_status', hasSeries ? 'Series configuradas' : 'Series pendientes');
    setText('#audit_logo_status', hasLogo ? 'Logo configurado' : 'Logo pendiente');
  };

  const setText = (selector, value) => {
    const el = qs(selector);
    if (el) el.textContent = value;
  };

  const fillEditModal = async (data) => {
    const form = qs('#rfcEditForm');
    if (form) {
      const template = form.getAttribute('data-action-template') || '';
      form.action = template.replace('__ID__', String(data.id || ''));
    }

    const meta = getMeta(data);
    const config = getConfig(data);
    const direccion = getAddress(data);
    const branding = getBranding(data);
    const email = getEmailConfig(data);
    const complements = getComplements(data);

    setValue('edit_rfc', data.rfc || '');
    setValue('edit_razon_social', data.razon_social || '');
    setValue('edit_nombre_comercial', config.nombre_comercial || data.nombre_comercial || '');
    setValue('edit_email', config.email || data.email || '');
    setValue('edit_telefono', config.telefono || '');
    setValue('edit_sitio_web', config.sitio_web || '');
    setValue('edit_regimen_fiscal', config.regimen_fiscal || data.regimen_fiscal || '');
    setValue('edit_tipo_origen', config.tipo_origen || data.tipo_origen || 'interno');
    setValue('edit_source_label', config.source_label || data.source_label || '');

    setValue('edit_codigo_postal', direccion.codigo_postal || '');
    await loadStates(modals.edit, direccion.estado || '');
    await loadMunicipalities(modals.edit, direccion.estado || '', direccion.municipio || '');

    const colonySelect = qs('#edit_colonia');
    if (colonySelect && direccion.colonia) {
      setSelectOptions(colonySelect, [direccion.colonia], 'Selecciona colonia', direccion.colonia);
    }

    if (direccion.codigo_postal) {
      await loadColonies(modals.edit, direccion.codigo_postal, direccion.colonia || '');
    }

    setValue('edit_calle', direccion.calle || '');
    setValue('edit_no_exterior', direccion.no_exterior || '');
    setValue('edit_no_interior', direccion.no_interior || '');

    setValue('edit_color_pdf', branding.color_pdf || '#2563eb');
    setValue('edit_plantilla_pdf', branding.plantilla_pdf || 'moderna');
    setValue('edit_leyenda_pdf', branding.leyenda_pdf || '');
    setValue('edit_notas_pdf', branding.notas_pdf || '');

    setValue('edit_correo_facturacion', email.correo_facturacion || '');
    setValue('edit_correo_cc', email.correo_cc || '');
    setValue('edit_correo_bcc', email.correo_bcc || '');
    setValue('edit_correo_asunto', email.correo_asunto || 'Envío de CFDI');
    setValue('edit_correo_mensaje', email.correo_mensaje || 'Adjunto encontrará su CFDI en PDF y XML.');

    setChecked('edit_adjuntar_pdf', email.adjuntar_pdf !== false);
    setChecked('edit_adjuntar_xml', email.adjuntar_xml !== false);
    setChecked('edit_enviar_copia_emisor', email.enviar_copia_emisor);

    setValue('edit_csd_serie', data.csd_serie || meta.csd_serie || '');
    setValue('edit_csd_vigencia_hasta', data.csd_vigencia_hasta || meta.csd_vigencia_hasta || '');

    qsa('[data-edit-complemento]').forEach((input) => {
      input.checked = complements.includes(input.value);
    });

    setText('#audit_rfc_status', data.rfc ? 'RFC activo' : 'RFC pendiente');
    setText('#audit_updated_at', meta.updated_at || meta.audit?.last_updated_at || 'Sin actualización');
    setText('#audit_email_status', (email.correo_facturacion || config.email || data.email) ? 'Correo configurado' : 'Correo pendiente');

    setCertStatus(data);
    refreshAi(modals.edit);
  };

  const renderSeries = (data) => {
    const list = qs('#seriesList');
    if (!list) return;

    const series = Array.isArray(getMeta(data).series) ? getMeta(data).series : [];

    if (!series.length) {
      list.innerHTML = `
        <div class="rfcs-series-item">
          <div>
            <strong>Sin series configuradas</strong>
            <span>Agrega una serie para controlar folios automáticos por RFC emisor.</span>
          </div>
        </div>
      `;
      return;
    }

    list.innerHTML = series.map((item) => {
      const serie = item.serie || 'Sin serie';
      const folio = item.folio_actual ?? 0;
      const tipo = item.tipo_comprobante || 'I';
      const descripcion = item.descripcion || 'Facturación general';
      const isDefault = item.is_default ? ' · Predeterminada' : '';

      return `
        <div class="rfcs-series-item">
          <div>
            <strong>${escapeHtml(serie)} · Folio ${escapeHtml(String(folio))}</strong>
            <span>${escapeHtml(descripcion)} · Tipo ${escapeHtml(tipo)}${escapeHtml(isDefault)}</span>
          </div>
          <span class="rfcs-badge ok">Activa</span>
        </div>
      `;
    }).join('');
  };

  const autoFillSuggestions = (modal) => {
    if (!modal) return;

    const email = qs('#edit_email', modal)?.value || qs('input[name="email"]', modal)?.value || '';
    const razon = qs('#edit_razon_social', modal)?.value || qs('input[name="razon_social"]', modal)?.value || '';
    const comercial = qs('#edit_nombre_comercial', modal) || qs('input[name="nombre_comercial"]', modal);
    const correoFacturacion = qs('#edit_correo_facturacion', modal) || qs('input[name="correo_facturacion"]', modal);
    const asunto = qs('#edit_correo_asunto', modal) || qs('input[name="correo_asunto"]', modal);
    const mensaje = qs('#edit_correo_mensaje', modal) || qs('textarea[name="correo_mensaje"]', modal);
    const leyenda = qs('#edit_leyenda_pdf', modal) || qs('input[name="leyenda_pdf"]', modal);
    const notas = qs('#edit_notas_pdf', modal) || qs('textarea[name="notas_pdf"]', modal);

    if (comercial && !comercial.value && razon) comercial.value = razon;
    if (correoFacturacion && !correoFacturacion.value && email) correoFacturacion.value = email;
    if (asunto && !asunto.value) asunto.value = 'Envío de CFDI';
    if (mensaje && !mensaje.value) mensaje.value = 'Adjunto encontrará su CFDI en PDF y XML.';
    if (leyenda && !leyenda.value) leyenda.value = 'Gracias por su preferencia.';
    if (notas && !notas.value) notas.value = 'Este documento es una representación impresa de un CFDI.';

    setChecked('edit_adjuntar_pdf', true);
    setChecked('edit_adjuntar_xml', true);

    refreshAi(modal);
  };

  const handleOpenButtons = () => {
    qsa('[data-open-rfc-modal]').forEach((button) => {
      button.addEventListener('click', async () => {
        const modalType = button.getAttribute('data-open-rfc-modal');

        if (modalType === 'create') {
          await openModal(modals.create);
          return;
        }

        const data = parsePayload(button);

        if (modalType === 'edit' || modalType === 'certs') {
          await fillEditModal(data);
          await openModal(modals.edit);

          if (modalType === 'certs') {
            activateTab(modals.edit, 'edit-certs');
          }

          return;
        }

        if (modalType === 'series') {
          await fillEditModal(data);
          renderSeries(data);
          await openModal(modals.edit);
          activateTab(modals.edit, 'edit-certs');
        }
      });
    });
  };

  const activateTab = (modal, target) => {
    if (!modal || !target) return;

    qsa('[data-rfcs-tab]', modal).forEach((item) => {
      item.classList.toggle('is-active', item.getAttribute('data-rfcs-tab') === target);
    });

    qsa('[data-rfcs-panel]', modal).forEach((panel) => {
      panel.classList.toggle('is-active', panel.getAttribute('data-rfcs-panel') === target);
    });
  };

  const handleCloseButtons = () => {
    qsa('[data-close-rfc-modal]').forEach((button) => {
      button.addEventListener('click', closeModals);
    });

    document.addEventListener('keydown', (event) => {
      if (event.key === 'Escape') closeModals();
    });
  };

  const handleFilters = () => {
    const searchInput = qs('#rfcsSearch');
    const statusFilter = qs('#rfcsStatusFilter');
    const rows = qsa('[data-rfc-row]');

    const apply = () => {
      const query = (searchInput?.value || '').trim().toLowerCase();
      const filter = statusFilter?.value || '';

      rows.forEach((row) => {
        const search = row.getAttribute('data-search') || '';
        const status = row.getAttribute('data-status') || '';
        const hasCsd = row.getAttribute('data-has-csd') === '1';
        const hasSeries = row.getAttribute('data-has-series') === '1';

        let visible = true;

        if (query && !search.includes(query)) visible = false;
        if (filter === 'activo' && status !== 'activo') visible = false;
        if (filter === 'inactivo' && status !== 'inactivo') visible = false;
        if (filter === 'sin_csd' && hasCsd) visible = false;
        if (filter === 'sin_series' && hasSeries) visible = false;

        row.style.display = visible ? '' : 'none';
      });
    };

    if (searchInput) searchInput.addEventListener('input', apply);
    if (statusFilter) statusFilter.addEventListener('change', apply);
  };

  const handleInputs = () => {
    document.addEventListener('input', (event) => {
      const input = event.target;

      if (input.matches('input[name="rfc"], input[name="serie"]')) {
        input.value = input.value.toUpperCase().replace(/\s+/g, '');
      }

      if (input.matches('[data-cp-autofill]')) {
        input.value = input.value.replace(/\D/g, '').slice(0, 5);
        if (input.value.length === 5) fillAddressByCp(input);
      }

      if (input.closest('.rfcs-modal')) {
        refreshAi(input.closest('.rfcs-modal'));
      }
    });

    document.addEventListener('change', async (event) => {
      const input = event.target;
      const modal = input.closest('.rfcs-modal');

      if (!modal) return;

      if (input.matches('[data-location-state]')) {
        await loadMunicipalities(modal, input.value);
        await loadColonies(modal);
      }

      if (input.matches('[data-location-municipality]')) {
        await loadColonies(modal);
      }

      refreshAi(modal);
    });
  };

  const handleTabs = () => {
    qsa('[data-rfcs-tab]').forEach((tab) => {
      tab.addEventListener('click', () => {
        activateTab(tab.closest('.rfcs-modal'), tab.getAttribute('data-rfcs-tab'));
      });
    });
  };

  const handleAiButton = () => {
    document.addEventListener('click', (event) => {
      const btn = event.target.closest('[data-ai-autofill]');
      if (!btn) return;
      autoFillSuggestions(btn.closest('.rfcs-modal'));
    });
  };

  document.addEventListener('DOMContentLoaded', async () => {
    handleOpenButtons();
    handleCloseButtons();
    handleFilters();
    handleInputs();
    handleTabs();
    handleAiButton();
  });
})();