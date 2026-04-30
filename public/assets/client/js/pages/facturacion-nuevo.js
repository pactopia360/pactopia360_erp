(function () {
  'use strict';

  const config = window.P360_CFDI_NUEVO || {};
  let PRODUCTOS = Array.isArray(config.productos) ? config.productos : [];

  const form = document.getElementById('newForm');
  const itemsBody = document.getElementById('itemsBody');
  const calcPreview = document.getElementById('calcPreview');
  const aiScore = document.getElementById('aiScore');
  const aiList = document.getElementById('aiList');
  const receptorSelect = document.getElementById('receptor_id');
  const receptorCard = document.getElementById('receptorSmartCard');

  if (!form || !itemsBody) {
    return;
  }

  function money(value) {
    return Number(value || 0).toLocaleString('es-MX', {
      minimumFractionDigits: 2,
      maximumFractionDigits: 2
    });
  }

  function esc(value) {
    return String(value || '')
      .replaceAll('&', '&amp;')
      .replaceAll('<', '&lt;')
      .replaceAll('>', '&gt;')
      .replaceAll('"', '&quot;')
      .replaceAll("'", '&#039;');
  }

  function fiscalSuggestion(text) {
  const raw = String(text || '').toLowerCase();

  if (!raw.trim()) {
    return {
      score: 25,
      clave: '01010101',
      claveLabel: 'Por definir',
      unidad: 'E48',
      unidadLabel: 'Unidad de servicio',
      objeto: '02',
      iva: 0.16,
      risk: 'Completa la descripción fiscal.'
    };
  }

  if (raw.includes('servicio') || raw.includes('consult') || raw.includes('asesor') || raw.includes('soporte') || raw.includes('desarrollo') || raw.includes('software')) {
    return {
      score: 86,
      clave: '81112100',
      claveLabel: 'Servicios informáticos / profesionales',
      unidad: 'E48',
      unidadLabel: 'Unidad de servicio',
      objeto: '02',
      iva: 0.16,
      risk: 'Servicio gravado. Revisa contrato/alcance.'
    };
  }

  if (raw.includes('producto') || raw.includes('pieza') || raw.includes('refaccion') || raw.includes('equipo') || raw.includes('material')) {
    return {
      score: 82,
      clave: '01010101',
      claveLabel: 'Producto por clasificar',
      unidad: 'H87',
      unidadLabel: 'Pieza',
      objeto: '02',
      iva: 0.16,
      risk: 'Producto físico. Confirma clave SAT exacta.'
    };
  }

  if (raw.includes('renta') || raw.includes('arrend')) {
    return {
      score: 78,
      clave: '80131500',
      claveLabel: 'Arrendamiento',
      unidad: 'E48',
      unidadLabel: 'Unidad de servicio',
      objeto: '02',
      iva: 0.16,
      risk: 'Revisa retenciones si aplica.'
    };
  }

  return {
    score: 65,
    clave: '01010101',
    claveLabel: 'Por clasificar',
    unidad: 'E48',
    unidadLabel: 'Unidad de servicio',
    objeto: '02',
    iva: 0.16,
    risk: 'Confirma clave SAT antes de timbrar.'
  };
}

function objectTaxLabel(value) {
  if (String(value) === '01') return 'No objeto';
  if (String(value) === '02') return 'Sí objeto';
  if (String(value) === '03') return 'Sí objeto y no obligado';
  return 'Pendiente';
}

  function rowTemplate(idx, data) {
    const item = Object.assign({
      producto_id: '',
      descripcion: '',
      unidad: 'E48',
      cantidad: 1,
      precio_unitario: 0,
      iva_tasa: 0.16,
      descuento: 0,
      clave_sat: '',
      objeto_impuesto: '02'
    }, data || {});

    const suggestion = fiscalSuggestion(item.descripcion);
    const claveSat = item.clave_sat || suggestion.clave;
    const objeto = item.objeto_impuesto || suggestion.objeto;

    const opts = PRODUCTOS.length
    ? PRODUCTOS.map(function (p) {
        const selected = String(item.producto_id) === String(p.id) ? 'selected' : '';
        return `<option value="${esc(p.id)}" ${selected}>${esc(p.label || p.descripcion || ('Producto #' + p.id))}</option>`;
      }).join('')
    : `<option value="" disabled>No hay productos registrados</option>`;

    return `
      <div data-idx="${idx}" class="concept-card-pro">
        <div class="concept-card-main">
          <div class="concept-card-icon">CFDI</div>

          <div class="concept-card-content">
            <label class="concept-field concept-field-full">
              <span>Descripción fiscal</span>
              <textarea class="textarea-cfdi concept-description"
                        name="conceptos[${idx}][descripcion]"
                        required
                        placeholder="Ej. Servicio mensual de software, asesoría, producto vendido...">${esc(item.descripcion)}</textarea>
            </label>

            <div class="concept-product-row">
              <label class="concept-field">
                <span>Producto guardado</span>
                <select class="select-cfdi concept-product"
                        name="conceptos[${idx}][producto_id]"
                        data-product-select="${idx}">
                  <option value="">Seleccionar producto</option>
                  ${opts}
                </select>
              </label>

              <button type="button" class="cfdi-link-mini" data-new-product>
                + Nuevo producto
              </button>
            </div>

            <div class="concept-ai-line">
              <span class="concept-chip ai">IA ${suggestion.score}/100</span>
              <span class="concept-chip">${esc(suggestion.risk)}</span>
            </div>
          </div>

          <button type="button" class="btn-mini danger" data-remove-row="${idx}" title="Eliminar">×</button>
        </div>

        <div class="concept-card-grid">
          <label class="concept-field">
            <span>Clave SAT</span>
            <input type="text"
                  class="input-cfdi"
                  name="conceptos[${idx}][clave_producto_sat]"
                  value="${esc(claveSat)}"
                  placeholder="01010101">
          </label>

          <label class="concept-field">
            <span>Unidad SAT</span>
            <select class="select-cfdi" name="conceptos[${idx}][clave_unidad_sat]">
              <option value="E48" ${item.unidad === 'E48' ? 'selected' : ''}>E48 · Servicio</option>
              <option value="H87" ${item.unidad === 'H87' ? 'selected' : ''}>H87 · Pieza</option>
              <option value="ACT" ${item.unidad === 'ACT' ? 'selected' : ''}>ACT · Actividad</option>
              <option value="KGM" ${item.unidad === 'KGM' ? 'selected' : ''}>KGM · Kilogramo</option>
            </select>
          </label>

          <label class="concept-field">
            <span>Objeto impuesto</span>
            <select class="select-cfdi" name="conceptos[${idx}][objeto_impuesto]">
              <option value="02" ${objeto === '02' ? 'selected' : ''}>02 · Sí objeto</option>
              <option value="01" ${objeto === '01' ? 'selected' : ''}>01 · No objeto</option>
              <option value="03" ${objeto === '03' ? 'selected' : ''}>03 · No obligado</option>
            </select>
          </label>

          <label class="concept-field">
            <span>Cantidad</span>
            <div class="qty-control concept-qty">
              <button type="button" data-step="-1">−</button>
              <input type="number"
                    step="0.0001"
                    min="0.0001"
                    name="conceptos[${idx}][cantidad]"
                    value="${esc(item.cantidad)}">
              <button type="button" data-step="1">+</button>
            </div>
          </label>

          <label class="concept-field">
            <span>Precio unitario</span>
            <input type="number"
                  step="0.0001"
                  min="0"
                  class="input-cfdi"
                  name="conceptos[${idx}][precio_unitario]"
                  value="${esc(item.precio_unitario)}"
                  placeholder="0.00">
          </label>

          <label class="concept-field">
            <span>Descuento</span>
            <input type="number"
                  step="0.01"
                  min="0"
                  class="input-cfdi"
                  name="conceptos[${idx}][descuento]"
                  value="${esc(item.descuento)}"
                  placeholder="0.00">
          </label>

          <label class="concept-field">
            <span>IVA</span>
            <select class="select-cfdi" name="conceptos[${idx}][iva_tasa]">
              <option value="0.16" ${Number(item.iva_tasa) === 0.16 ? 'selected' : ''}>16%</option>
              <option value="0.08" ${Number(item.iva_tasa) === 0.08 ? 'selected' : ''}>8%</option>
              <option value="0" ${Number(item.iva_tasa) === 0 ? 'selected' : ''}>0%</option>
            </select>
          </label>

          <div class="concept-total-box">
            <span>Total</span>
            <strong class="total concept-total">$0.00</strong>
          </div>
        </div>
      </div>
    `;
  }


  function getTotals() {
    let subtotal = 0;
    let iva = 0;
    let total = 0;

    Array.from(itemsBody.children).forEach(function (tr, i) {
      const q = parseFloat(tr.querySelector(`[name="conceptos[${i}][cantidad]"]`)?.value || '0');
      const pu = parseFloat(tr.querySelector(`[name="conceptos[${i}][precio_unitario]"]`)?.value || '0');
      const t = parseFloat(tr.querySelector(`[name="conceptos[${i}][iva_tasa]"]`)?.value || '0');

      const rowSubtotal = Math.round((q * pu) * 100) / 100;
      const rowIva = Math.round((rowSubtotal * t) * 100) / 100;
      const rowTotal = Math.round((rowSubtotal + rowIva) * 100) / 100;

      subtotal += rowSubtotal;
      iva += rowIva;
      total += rowTotal;

      const totalCell = tr.querySelector('.total');
      if (totalCell) {
        totalCell.textContent = '$' + money(rowTotal);
      }
    });

    return { subtotal, iva, total };
  }

  function setText(selector, value) {
    const el = document.querySelector(selector);
    if (el) {
      el.textContent = value;
    }
  }

  function recalc() {
    const totals = getTotals();

    if (calcPreview) {
      calcPreview.textContent = `Subtotal: $${money(totals.subtotal)} · IVA: $${money(totals.iva)} · Total: $${money(totals.total)}`;
    }

    setText('[data-cfdi-subtotal]', '$' + money(totals.subtotal));
    setText('[data-cfdi-iva]', '$' + money(totals.iva));
    setText('[data-cfdi-total]', '$' + money(totals.total) + ' MXN');

    updateAI();
  }

  function addItem(data) {
    const idx = itemsBody.children.length;
    itemsBody.insertAdjacentHTML('beforeend', rowTemplate(idx, data));
    recalc();
  }


  function normalizeRows() {
  Array.from(itemsBody.children).forEach(function (row, i) {
    row.dataset.idx = String(i);

    row.querySelectorAll('[name]').forEach(function (field) {
      field.name = field.name.replace(/conceptos\[\d+\]/g, `conceptos[${i}]`);
    });

    const productSelect = row.querySelector('[data-product-select]');
    if (productSelect) {
      productSelect.dataset.productSelect = String(i);
    }

    const remove = row.querySelector('[data-remove-row]');
    if (remove) {
      remove.dataset.removeRow = String(i);
    }
  });
}

function removeItem(idx) {
  const row = itemsBody.querySelector(`[data-idx="${idx}"]`);

  if (!row) {
    return;
  }

  row.remove();
  normalizeRows();

  if (!itemsBody.children.length) {
    addItem({
      cantidad: 1,
      precio_unitario: 0,
      iva_tasa: 0.16
    });
  }

  recalc();
}

function onProductChange(idx, pid) {
  const product = PRODUCTOS.find(function (p) {
    return String(p.id) === String(pid);
  });

  const row = itemsBody.querySelector(`[data-idx="${idx}"]`);

  if (!product || !row) {
    return;
  }

  const descripcion = row.querySelector(`[name="conceptos[${idx}][descripcion]"]`);
  const precio = row.querySelector(`[name="conceptos[${idx}][precio_unitario]"]`);
  const iva = row.querySelector(`[name="conceptos[${idx}][iva_tasa]"]`);
  const unidad = row.querySelector(`[name="conceptos[${idx}][clave_unidad_sat]"]`);
  const claveSat = row.querySelector(`[name="conceptos[${idx}][clave_producto_sat]"]`);
  const objeto = row.querySelector(`[name="conceptos[${idx}][objeto_impuesto]"]`);

  if (descripcion && product.descripcion) descripcion.value = product.descripcion;
  if (precio) precio.value = product.precio_unitario || 0;
  if (iva) iva.value = product.iva_tasa ?? 0.16;
  if (unidad && product.unidad) unidad.value = product.unidad;
  if (claveSat && product.clave_sat) claveSat.value = product.clave_sat;
  if (objeto && product.objeto_impuesto) objeto.value = product.objeto_impuesto;

  recalc();
}

  function updateReceptorCard() {
  if (!receptorCard) {
    return;
  }

  const tipo = getTipoCfdi();
  const cpInput = document.getElementById('cp_receptor');
  const regimenInput = document.getElementById('regimen_receptor');
  const usoInput = document.getElementById('uso_cfdi');

  if (tipo === 'N') {
    const empleadoSelect = document.getElementById('empleado_nomina_id');
    const opt = empleadoSelect?.options[empleadoSelect.selectedIndex];

    if (!opt || !empleadoSelect.value) {
      receptorCard.innerHTML = `
        <strong>Empleado pendiente</strong>
        <span>Selecciona un empleado para generar CFDI de nómina. No uses receptores comerciales.</span>
      `;
      return;
    }

    const rfc = opt.dataset.rfc || '';
    const nombre = opt.dataset.nombre || opt.textContent || '';
    const cp = opt.dataset.cp || '';
    const regimen = opt.dataset.regimen || '605';
    const puesto = opt.dataset.puesto || '';
    const departamento = opt.dataset.departamento || '';

    if (cpInput) cpInput.value = cp;
    if (regimenInput) regimenInput.value = regimen;
    if (usoInput) usoInput.value = 'CN01';

    const nominaCpPreview = document.getElementById('nomina_cp_preview');
    const nominaRegimenPreview = document.getElementById('nomina_regimen_preview');

    if (nominaCpPreview) nominaCpPreview.value = cp;
    if (nominaRegimenPreview) nominaRegimenPreview.value = regimen;

    receptorCard.innerHTML = `
      <strong>${esc(nombre)}</strong>
      <span>RFC: ${esc(rfc || 'pendiente')} · Régimen: ${esc(regimen || '605')} · CP: ${esc(cp || 'pendiente')} · ${esc(departamento || 'Sin departamento')} ${puesto ? '· ' + esc(puesto) : ''}</span>
    `;
    return;
  }

  if (!receptorSelect) {
    return;
  }

  const opt = receptorSelect.options[receptorSelect.selectedIndex];

  if (!opt || !receptorSelect.value) {
    receptorCard.innerHTML = `
      <strong>Ficha fiscal inteligente</strong>
      <span>Selecciona un receptor para validar RFC, régimen y CP antes de timbrar.</span>
    `;
    return;
  }

  const rfc = opt.dataset.rfc || '';
  const nombre = opt.dataset.nombre || opt.textContent || '';
  const cp = opt.dataset.cp || '';
  const regimen = opt.dataset.regimen || '';

  if (cpInput && !cpInput.value && cp) {
    cpInput.value = cp;
  }

  if (regimenInput && !regimenInput.value && regimen) {
    regimenInput.value = regimen;
  }

  receptorCard.innerHTML = `
    <strong>${esc(nombre)}</strong>
    <span>RFC: ${esc(rfc || 'pendiente')} · Régimen: ${esc(regimen || 'pendiente')} · CP: ${esc(cp || 'pendiente')}</span>
  `;
}

  function setReview(key, ok, text) {
    const el = document.querySelector(`[data-review="${key}"]`);

    if (!el) {
      return;
    }

    el.classList.toggle('ok', ok);
    el.textContent = text;
  }

  function updateAI() {
    const clienteId = document.getElementById('cliente_id')?.value || '';
    const receptorId = document.getElementById('receptor_id')?.value || '';
    const metodo = document.getElementById('metodo_pago')?.value || '';
    const forma = document.getElementById('forma_pago')?.value || '';
    const cp = document.getElementById('cp_receptor')?.value || '';
    const regimen = document.getElementById('regimen_receptor')?.value || '';
    const totals = getTotals();

    let score = 50;
    const items = [];

    if (clienteId) {
      score += 12;
      items.push(['ok', 'Emisor seleccionado', 'RFC válido']);
      setReview('emisor', true, 'Emisor completo');
    } else {
      items.push(['warn', 'Emisor pendiente', 'Selecciona un emisor activo']);
      setReview('emisor', false, 'Emisor pendiente');
    }

    if (receptorId) {
      score += 12;
      items.push(['ok', 'Receptor fiscal', 'RFC y régimen detectados']);
      setReview('receptor', true, 'Receptor seleccionado');
    } else {
      items.push(['warn', 'Receptor fiscal', 'Selecciona receptor fiscal']);
      setReview('receptor', false, 'Receptor pendiente');
    }

    if (cp && cp.length === 5) {
      score += 7;
      items.push(['ok', 'Código postal fiscal', 'CP con 5 dígitos']);
    } else {
      items.push(['warn', 'Código postal fiscal', 'Debe tener 5 dígitos']);
    }

    if (regimen) {
      score += 7;
      items.push(['ok', 'Régimen fiscal', 'Capturado correctamente']);
    } else {
      items.push(['warn', 'Régimen fiscal', 'Falta régimen del receptor']);
    }

    if (totals.total > 0) {
      score += 7;
      items.push(['ok', 'Conceptos', 'Importe calculado correctamente']);
      setReview('conceptos', true, 'Conceptos completos');
    } else {
      items.push(['neutral', 'Conceptos', 'Agrega conceptos al comprobante']);
      setReview('conceptos', false, 'Conceptos pendientes');
    }

    if (metodo && forma) {
      score += 5;
      items.push(['ok', 'Pago', 'Método y forma definidos']);
      setReview('pago', true, 'Pago completo');
    } else {
      items.push(['warn', 'Método / forma de pago', 'Selecciona método y forma']);
      setReview('pago', false, 'Pago pendiente');
    }

    score = Math.max(0, Math.min(100, score));

    if (aiScore) {
      aiScore.textContent = String(score);
    }

    if (aiList) {
      aiList.innerHTML = items.slice(0, 7).map(function (item) {
        const icon = item[0] === 'ok' ? '✓' : item[0] === 'warn' ? '!' : '•';

        return `
          <div class="ai-item ${item[0]}">
            <span>${icon}</span>
            <div>
              <strong>${esc(item[1])}</strong>
              <small>${esc(item[2])}</small>
            </div>
          </div>
        `;
      }).join('');
    }
  }

  document.querySelectorAll('[data-step-target]').forEach(function (btn) {
    btn.addEventListener('click', function () {
      document.querySelectorAll('.cfdi-step').forEach(function (x) {
        x.classList.remove('active');
      });

      btn.classList.add('active');

      const target = document.getElementById(btn.dataset.stepTarget);

      if (target) {
        target.scrollIntoView({
          behavior: 'smooth',
          block: 'start'
        });
      }
    });
  });

  itemsBody.addEventListener('click', function (event) {
    const removeBtn = event.target.closest('[data-remove-row]');

    if (removeBtn) {
      removeItem(removeBtn.dataset.removeRow);
      return;
    }

    const qtyBtn = event.target.closest('.qty-control button');

    if (qtyBtn) {
      const step = parseFloat(qtyBtn.dataset.step || '0');
      const input = qtyBtn.parentElement.querySelector('input[type="number"]');

      if (!input) {
        return;
      }

      let value = parseFloat(input.value || '0');

      if (Number.isNaN(value)) {
        value = 0;
      }

      value += step;

      if (value < 0.0001) {
        value = 0.0001;
      }

      input.value = value.toFixed(4).replace(/\.?0+$/, '');
      recalc();
      return;
    }

    const newProduct = event.target.closest('[data-new-product]');

    if (newProduct) {
      openProductModal();
      return;
    }
  });

  itemsBody.addEventListener('input', function (event) {
    if (event.target.matches('input, textarea, select')) {
      recalc();
    }
  });

  itemsBody.addEventListener('change', function (event) {
    const select = event.target.closest('[data-product-select]');

    if (select) {
      onProductChange(select.dataset.productSelect, select.value);
      return;
    }

    recalc();
  });

  document.getElementById('btnAddConcept')?.addEventListener('click', function () {
    addItem({
      cantidad: 1,
      precio_unitario: 0,
      iva_tasa: 0.16
    });
  });

  document.getElementById('btnAddConceptInline')?.addEventListener('click', function () {
    addItem({
      cantidad: 1,
      precio_unitario: 0,
      iva_tasa: 0.16
    });
  });

  document.getElementById('complementsGrid')?.addEventListener('click', function (event) {
    const pill = event.target.closest('.comp-pill');

    if (!pill || pill.classList.contains('disabled')) {
      return;
    }

    const checkbox = pill.querySelector('input[type="checkbox"]');

    if (!checkbox) {
      return;
    }

    checkbox.checked = !checkbox.checked;
    pill.classList.toggle('active', checkbox.checked);
  });

    const adendaActiva = document.getElementById('adenda_activa');
  const adendaBody = document.getElementById('adendaBody');
  const adendaTipo = document.getElementById('adenda_tipo');
  const adendaOrden = document.getElementById('adenda_orden_compra');
  const adendaProveedor = document.getElementById('adenda_numero_proveedor');
  const adendaHelp = document.getElementById('adendaHelp');

  function syncAdenda() {
    if (!adendaActiva || !adendaBody) return;

    adendaBody.hidden = !adendaActiva.checked;

    if (!adendaActiva.checked) {
      if (adendaTipo) adendaTipo.value = '';
      return;
    }

    const tipo = adendaTipo?.value || '';
    const orden = adendaOrden?.value || '';
    const proveedor = adendaProveedor?.value || '';

    if (adendaHelp) {
      if (!tipo) {
        adendaHelp.textContent = 'Selecciona el tipo de adenda para activar los datos comerciales.';
      } else if (!orden || !proveedor) {
        adendaHelp.textContent = 'Para adendas corporativas se recomienda capturar orden de compra y número de proveedor.';
      } else {
        adendaHelp.textContent = 'Adenda lista para guardarse junto con el CFDI.';
      }
    }
  }

  adendaActiva?.addEventListener('change', function () {
    syncAdenda();
    updateAI();
  });

  adendaTipo?.addEventListener('change', function () {
    syncAdenda();
    updateAI();
  });

  document.querySelectorAll('[name^="adenda["]').forEach(function (field) {
    field.addEventListener('input', function () {
      syncAdenda();
      updateAI();
    });
  });

  syncAdenda();

  form.addEventListener('input', updateAI);
  form.addEventListener('change', updateAI);

  receptorSelect?.addEventListener('change', function () {
    updateReceptorCard();
    updateAI();
  });

  document.getElementById('empleado_nomina_id')?.addEventListener('change', function () {
  updateReceptorCard();
  updateAI();
});

  document.getElementById('btnPreview')?.addEventListener('click', function () {
    updateAI();

    const revision = document.getElementById('revision');

    if (revision) {
      revision.scrollIntoView({
        behavior: 'smooth',
        block: 'start'
      });
    }
  });


  const productModal = document.getElementById('productModal');
const productList = document.getElementById('productList');
const productForm = document.getElementById('productForm');
const productSearch = document.getElementById('productSearch');

function productPayloadFromForm() {
  return {
    sku: document.getElementById('product_sku')?.value || '',
    descripcion: document.getElementById('product_descripcion')?.value || '',
    precio_unitario: document.getElementById('product_precio')?.value || 0,
    clave_prodserv: document.getElementById('product_clave')?.value || '01010101',
    clave_unidad: document.getElementById('product_unidad')?.value || 'E48',
    iva_tasa: document.getElementById('product_iva')?.value || 0.16,
    activo: document.getElementById('product_activo')?.checked ? 1 : 0
  };
}

function productSuggest(text) {
  const raw = String(text || '').toLowerCase();

  if (raw.includes('soporte') || raw.includes('software') || raw.includes('mantenimiento') || raw.includes('servicio')) {
    return { clave: '81112100', unidad: 'E48', iva: '0.16', text: 'Sugerencia: servicio gravado, unidad E48, IVA 16%.' };
  }

  if (raw.includes('pieza') || raw.includes('producto') || raw.includes('equipo')) {
    return { clave: '01010101', unidad: 'H87', iva: '0.16', text: 'Sugerencia: producto físico, confirma clave SAT exacta.' };
  }

  return { clave: '01010101', unidad: 'E48', iva: '0.16', text: 'Sugerencia base: confirma clave SAT antes de timbrar.' };
}

function applyProductAi() {
  const desc = document.getElementById('product_descripcion')?.value || '';
  const s = productSuggest(desc);

  if (document.getElementById('product_clave')) document.getElementById('product_clave').value = s.clave;
  if (document.getElementById('product_unidad')) document.getElementById('product_unidad').value = s.unidad;
  if (document.getElementById('product_iva')) document.getElementById('product_iva').value = s.iva;
  if (document.getElementById('productAiText')) document.getElementById('productAiText').textContent = s.text;
}

function renderProducts(filter) {
  if (!productList) return;

  const term = String(filter || '').toLowerCase();

  const rows = PRODUCTOS.filter(function (p) {
    return !term
      || String(p.descripcion || '').toLowerCase().includes(term)
      || String(p.sku || '').toLowerCase().includes(term)
      || String(p.clave_sat || p.clave_prodserv || '').toLowerCase().includes(term);
  });

  if (!rows.length) {
    productList.innerHTML = `<div class="cfdi-product-empty">No hay productos registrados.</div>`;
    refreshProductSelects();
    return;
  }

  productList.innerHTML = rows.map(function (p) {
    return `
      <div class="cfdi-product-item" data-product-id="${esc(p.id)}">
        <div>
          <strong>${esc(p.descripcion || 'Sin descripción')}</strong>
          <span>${esc(p.sku || 'Sin SKU')} · SAT ${esc(p.clave_sat || p.clave_prodserv || '01010101')} · ${esc(p.unidad || p.clave_unidad || 'E48')} · IVA ${Number(p.iva_tasa ?? 0.16) * 100}%</span>
        </div>

        <div class="cfdi-product-item-actions">
          <button type="button" data-product-select-modal="${esc(p.id)}">Usar</button>
          <button type="button" data-product-edit="${esc(p.id)}">Editar</button>
          <button type="button" data-product-delete="${esc(p.id)}">Eliminar</button>
        </div>
      </div>
    `;
  }).join('');

  refreshProductSelects();
}

function refreshProductSelects() {
  document.querySelectorAll('[data-product-select]').forEach(function (select) {
    const current = select.value;

    select.innerHTML = `<option value="">Seleccionar producto</option>` + PRODUCTOS.map(function (p) {
      return `<option value="${esc(p.id)}">${esc(p.label || p.descripcion || ('Producto #' + p.id))}</option>`;
    }).join('');

    select.value = current;
  });
}

function resetProductForm() {
  if (!productForm) return;

  productForm.reset();
  document.getElementById('product_id').value = '';
  document.getElementById('product_clave').value = '01010101';
  document.getElementById('product_unidad').value = 'E48';
  document.getElementById('product_iva').value = '0.16';
  document.getElementById('product_activo').checked = true;
  document.getElementById('productAiText').textContent = 'Escribe una descripción para sugerir clave SAT, unidad e IVA.';
}

function openProductModal() {
  if (!productModal) return;

  productModal.classList.add('is-open');
  productModal.setAttribute('aria-hidden', 'false');
  document.body.classList.add('cfdi-product-modal-open');
  renderProducts(productSearch?.value || '');
}

function closeProductModal() {
  if (!productModal) return;

  productModal.classList.remove('is-open');
  productModal.setAttribute('aria-hidden', 'true');
  document.body.classList.remove('cfdi-product-modal-open');
}

async function loadProducts() {
  try {
    const res = await fetch(config.productosUrl, {
      headers: { Accept: 'application/json' }
    });

    const json = await res.json();

    if (json.ok && Array.isArray(json.productos)) {
      PRODUCTOS = json.productos;
      renderProducts(productSearch?.value || '');
    }
  } catch (error) {
    console.warn('No se pudo cargar productos.', error);
  }
}

async function saveProduct(event) {
  event.preventDefault();

  const id = document.getElementById('product_id')?.value || '';
  const url = id
    ? config.productosUpdateUrl.replace('__ID__', id)
    : config.productosStoreUrl;

  const method = id ? 'PUT' : 'POST';

  try {
    const res = await fetch(url, {
      method,
      headers: {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        'X-CSRF-TOKEN': config.csrf
      },
      body: JSON.stringify(productPayloadFromForm())
    });

    const json = await res.json();

    if (!res.ok || !json.ok) {
      alert(json.message || 'No se pudo guardar el producto.');
      return;
    }

    await loadProducts();
    resetProductForm();
  } catch (error) {
    alert('Error al guardar producto.');
  }
}

function editProduct(id) {
  const p = PRODUCTOS.find(function (item) {
    return String(item.id) === String(id);
  });

  if (!p) return;

  document.getElementById('product_id').value = p.id;
  document.getElementById('product_sku').value = p.sku || '';
  document.getElementById('product_descripcion').value = p.descripcion || '';
  document.getElementById('product_precio').value = p.precio_unitario || 0;
  document.getElementById('product_clave').value = p.clave_sat || p.clave_prodserv || '01010101';
  document.getElementById('product_unidad').value = p.unidad || p.clave_unidad || 'E48';
  document.getElementById('product_iva').value = p.iva_tasa ?? 0.16;
  document.getElementById('product_activo').checked = p.activo !== false;

  applyProductAi();
}

async function deleteProduct(id) {
  if (!confirm('¿Eliminar producto?')) return;

  try {
    const res = await fetch(config.productosDeleteUrl.replace('__ID__', id), {
      method: 'DELETE',
      headers: {
        Accept: 'application/json',
        'X-CSRF-TOKEN': config.csrf
      }
    });

    const json = await res.json();

    if (!res.ok || !json.ok) {
      alert(json.message || 'No se pudo eliminar.');
      return;
    }

    await loadProducts();
  } catch (error) {
    alert('Error al eliminar producto.');
  }
}

function selectProductFromModal(id) {
  const firstSelect = itemsBody.querySelector('[data-product-select]');
  if (!firstSelect) return;

  firstSelect.value = id;
  onProductChange(firstSelect.dataset.productSelect, id);
  closeProductModal();
}

document.querySelectorAll('[data-close-product-modal]').forEach(function (btn) {
  btn.addEventListener('click', closeProductModal);
});

productForm?.addEventListener('submit', saveProduct);

document.getElementById('btnProductReset')?.addEventListener('click', resetProductForm);

document.getElementById('product_descripcion')?.addEventListener('input', applyProductAi);

document.getElementById('product_ai_query')?.addEventListener('input', applyProductAi);

document.getElementById('productSatResults')?.addEventListener('click', function (event) {
  const card = event.target.closest('[data-sat-clave]');

  if (!card) {
    return;
  }

  applySatSuggestion(card);
});

productSearch?.addEventListener('input', function () {
  renderProducts(productSearch.value);
});

productList?.addEventListener('click', function (event) {
  const useBtn = event.target.closest('[data-product-select-modal]');
  const editBtn = event.target.closest('[data-product-edit]');
  const delBtn = event.target.closest('[data-product-delete]');

  if (useBtn) selectProductFromModal(useBtn.dataset.productSelectModal);
  if (editBtn) editProduct(editBtn.dataset.productEdit);
  if (delBtn) deleteProduct(delBtn.dataset.productDelete);
});

/* =========================================================
   P360 · Flujo limpio por tipo de comprobante
========================================================= */

const tipoDocumentoInput =
  document.getElementById('tipo_documento') ||
  document.getElementById('tipo_comprobante');

const metodoPagoInput = document.getElementById('metodo_pago');
const formaPagoInput = document.getElementById('forma_pago');
const usoCfdiInput = document.getElementById('uso_cfdi');

function getTipoCfdi() {
  return String(tipoDocumentoInput?.value || 'I').toUpperCase();
}

function setSectionVisible(id, visible) {
  const el = document.getElementById(id);
  if (!el) return;

  el.hidden = !visible;
  el.classList.toggle('is-hidden', !visible);
}

function syncTipoComprobanteUi() {
  const tipo = getTipoCfdi();

  const isIngreso = tipo === 'I';
  const isPago = tipo === 'P';
  const isNomina = tipo === 'N';
  const isTraslado = tipo === 'T';
  const isEgreso = tipo === 'E';

  document.body.dataset.cfdiTipo = tipo;

  const receptorClientePanel = document.getElementById('receptorClientePanel');
  const receptorNominaPanel = document.getElementById('receptorNominaPanel');
  const empleadoNominaSelect = document.getElementById('empleado_nomina_id');
  const receptorModeTitle = document.getElementById('receptorModeTitle');
  const receptorModeText = document.getElementById('receptorModeText');

  if (receptorClientePanel && receptorNominaPanel) {
    receptorClientePanel.hidden = isNomina;
    receptorClientePanel.classList.toggle('is-hidden', isNomina);

    receptorNominaPanel.hidden = !isNomina;
    receptorNominaPanel.classList.toggle('is-hidden', !isNomina);
  }

  if (receptorSelect) {
    receptorSelect.required = !isNomina;
    receptorSelect.disabled = isNomina;
  }

  if (empleadoNominaSelect) {
    empleadoNominaSelect.required = isNomina;
    empleadoNominaSelect.disabled = !isNomina;
  }

  if (receptorModeTitle && receptorModeText) {
    receptorModeTitle.textContent = isNomina ? 'Empleado de nómina' : 'Cliente fiscal';
    receptorModeText.textContent = isNomina
      ? 'Nómina usa empleados, no receptores comerciales. Se bloquean adendas y complementos no aplicables.'
      : 'Para este CFDI se usa el catálogo normal de receptores fiscales.';
  }

  if (isNomina && usoCfdiInput) {
    usoCfdiInput.value = 'CN01';
  }

  updateReceptorCard();

  const tipoCard = document.getElementById('tipoCfdiSmartCard');
  const receptorTitle = document.querySelector('#receptor h2');
  const receptorDesc = document.querySelector('#receptor summary p');
  const conceptosTitle = document.querySelector('#conceptos h2');
  const conceptosDesc = document.querySelector('#conceptos summary p');
  const heroTitle = document.querySelector('.cfdi-title-row h1');

  const typeCopy = {
    I: {
      title: 'Factura de ingreso',
      desc: 'Flujo normal: cliente fiscal, productos o servicios, método de pago y timbrado.',
      receptor: 'Receptor',
      receptorDesc: 'Cliente fiscal del CFDI.',
      conceptos: 'Conceptos',
      conceptosDesc: 'Productos o servicios.'
    },
    E: {
      title: 'CFDI de egreso',
      desc: 'Usa este flujo para notas de crédito, devoluciones o descuentos relacionados a un CFDI previo.',
      receptor: 'Receptor',
      receptorDesc: 'Cliente fiscal relacionado al CFDI original.',
      conceptos: 'Conceptos de egreso',
      conceptosDesc: 'Conceptos que disminuyen o corrigen el CFDI relacionado.'
    },
    P: {
      title: 'Complemento de pago REP',
      desc: 'Selecciona CFDIs PPD pendientes y registra el pago sin conceptos normales ni adenda.',
      receptor: 'Receptor del pago',
      receptorDesc: 'Cliente con CFDIs PPD pendientes.',
      conceptos: 'Documentos relacionados',
      conceptosDesc: 'Aquí después conectaremos las facturas PPD con saldo pendiente.'
    },
    T: {
      title: 'Traslado / Carta Porte',
      desc: 'Flujo especial para traslado de mercancías. Requiere datos logísticos y complemento Carta Porte.',
      receptor: 'Receptor / Destino',
      receptorDesc: 'Datos fiscales o destino del traslado.',
      conceptos: 'Mercancías',
      conceptosDesc: 'Bienes transportados, no servicios normales.'
    },
    N: {
      title: 'Recibo de nómina',
      desc: 'Flujo exclusivo para empleados, percepciones, deducciones y complemento de nómina.',
      receptor: 'Empleado',
      receptorDesc: 'Receptor de nómina. No usa la lista normal de clientes.',
      conceptos: 'Percepciones y deducciones',
      conceptosDesc: 'No son productos ni servicios normales.'
    }
  };

  const copy = typeCopy[tipo] || typeCopy.I;

  if (heroTitle) heroTitle.textContent = 'Nuevo CFDI · ' + copy.title;
  if (tipoCard) {
    tipoCard.innerHTML = `
      <strong>${esc(copy.title)}</strong>
      <span>${esc(copy.desc)}</span>
    `;
  }

  if (receptorTitle) receptorTitle.textContent = copy.receptor;
  if (receptorDesc) receptorDesc.textContent = copy.receptorDesc;
  if (conceptosTitle) conceptosTitle.textContent = copy.conceptos;
  if (conceptosDesc) conceptosDesc.textContent = copy.conceptosDesc;

  setSectionVisible('conceptos', true);
  setSectionVisible('revision', true);

  const adendaCard = document.getElementById('adenda');
  const adendaCheck = document.getElementById('adenda_activa');
  const complementosAccordion = document.getElementById('complementosAccordion');

  if (adendaCard) {
    const allowAdenda = isIngreso || isEgreso;
    adendaCard.hidden = !allowAdenda;
    adendaCard.classList.toggle('is-hidden', !allowAdenda);

    if (!allowAdenda && adendaCheck) {
      adendaCheck.checked = false;
      syncAdenda();
    }
  }

  document.querySelectorAll('.comp-pill').forEach(function (pill) {
    const key = String(pill.dataset.complemento || '').toLowerCase();
    const input = pill.querySelector('input[type="checkbox"]');

    let allowed = isIngreso || isEgreso;

    if (isPago) allowed = key === 'pagos';
    if (isNomina) allowed = key === 'nomina';
    if (isTraslado) allowed = key === 'carta_porte';

    pill.classList.toggle('disabled', !allowed);

    if (input) {
      input.disabled = !allowed;

      if (!allowed) {
        input.checked = false;
        pill.classList.remove('active');
      }

      if ((isPago && key === 'pagos') || (isNomina && key === 'nomina') || (isTraslado && key === 'carta_porte')) {
        input.checked = true;
        pill.classList.add('active');
      }
    }
  });

  if (complementosAccordion) {
    complementosAccordion.open = isPago || isNomina || isTraslado;
  }

  if (isPago) {
    if (metodoPagoInput) metodoPagoInput.value = 'PPD';
    if (formaPagoInput) formaPagoInput.value = '99';
    if (usoCfdiInput) usoCfdiInput.value = 'CP01';
  }

  if (isNomina) {
    if (metodoPagoInput) metodoPagoInput.value = 'PUE';
    if (formaPagoInput) formaPagoInput.value = '99';
    if (usoCfdiInput) usoCfdiInput.value = 'CN01';
  }

  if (isTraslado) {
    if (metodoPagoInput) metodoPagoInput.value = '';
    if (formaPagoInput) formaPagoInput.value = '';
    if (usoCfdiInput) usoCfdiInput.value = 'S01';
  }

  if (isEgreso) {
    if (usoCfdiInput && !usoCfdiInput.value) usoCfdiInput.value = 'G02';
  }

  updateAI();
}

tipoDocumentoInput?.addEventListener('change', function () {
  syncTipoComprobanteUi();
  recalc();
});

/* =========================================================
   P360 · Acción real del formulario
   Guardar = borrador / Timbrar = PAC
========================================================= */

const accionCfdiInput = document.getElementById('accion_cfdi');

function setCfdiAction(action) {
  if (!accionCfdiInput) return;
  accionCfdiInput.value = action === 'timbrar' ? 'timbrar' : 'borrador';
}

document.querySelectorAll('[data-cfdi-action]').forEach(function (button) {
  button.addEventListener('click', function () {
    setCfdiAction(button.dataset.cfdiAction || 'borrador');
  });
});

form.addEventListener('submit', function (event) {
  const submitter = event.submitter || document.activeElement;
  const action = submitter?.dataset?.cfdiAction || accionCfdiInput?.value || 'borrador';

  setCfdiAction(action);

  if (action !== 'timbrar') {
    return;
  }

  const tipo = getTipoCfdi();
  const label = {
    I: 'factura de ingreso',
    E: 'nota de crédito / egreso',
    T: 'comprobante de traslado',
    P: 'complemento de pago',
    N: 'recibo de nómina'
  }[tipo] || 'CFDI';

  const ok = confirm(`¿Confirmas timbrar este ${label}? Se enviará al PAC y, si se timbra correctamente, se enviará por correo.`);

  if (!ok) {
    event.preventDefault();
    setCfdiAction('borrador');
    return;
  }

  submitter?.setAttribute('disabled', 'disabled');
  submitter?.classList.add('is-loading');
});

syncTipoComprobanteUi();

  if (!itemsBody.children.length) {
    addItem({
      cantidad: 1,
      precio_unitario: 0,
      iva_tasa: 0.16
    });
  }

  updateReceptorCard();
  recalc();
})();