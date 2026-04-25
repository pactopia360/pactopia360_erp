{{-- resources/views/cliente/rfcs/partials/modals.blade.php --}}

@php
    $regimenesSat = [
        '601' => 'General de Ley Personas Morales',
        '603' => 'Personas Morales con Fines no Lucrativos',
        '605' => 'Sueldos y Salarios e Ingresos Asimilados a Salarios',
        '606' => 'Arrendamiento',
        '607' => 'Régimen de Enajenación o Adquisición de Bienes',
        '608' => 'Demás ingresos',
        '610' => 'Residentes en el Extranjero sin Establecimiento Permanente en México',
        '611' => 'Ingresos por Dividendos',
        '612' => 'Personas Físicas con Actividades Empresariales y Profesionales',
        '614' => 'Ingresos por intereses',
        '615' => 'Régimen de los ingresos por obtención de premios',
        '616' => 'Sin obligaciones fiscales',
        '620' => 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos',
        '621' => 'Incorporación Fiscal',
        '622' => 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras',
        '623' => 'Opcional para Grupos de Sociedades',
        '624' => 'Coordinados',
        '625' => 'Actividades Empresariales con ingresos a través de Plataformas Tecnológicas',
        '626' => 'Régimen Simplificado de Confianza',
    ];

    $complementosRfc = [
        'pagos' => 'Complemento de pagos / REP',
        'carta_porte' => 'Carta Porte',
        'nomina' => 'Nómina',
        'comercio_exterior' => 'Comercio exterior',
        'retenciones' => 'Retenciones',
        'factura_global' => 'Factura global',
        'publico_general' => 'Público en general',
        'exportacion' => 'Exportación',
    ];
@endphp

{{-- MODAL CREAR RFC --}}
<div class="rfcs-modal" id="rfcCreateModal" aria-hidden="true">
    <div class="rfcs-modal-backdrop" data-close-rfc-modal></div>

    <div class="rfcs-modal-dialog xl">
        <div class="rfcs-modal-head">
            <div>
                <h2>Agregar RFC emisor</h2>
                <p>Alta completa del perfil fiscal, certificados, series, PDF, correo y complementos.</p>
            </div>

            <button type="button" class="rfcs-modal-close" data-close-rfc-modal>×</button>
        </div>

        <form method="POST" action="{{ route('cliente.rfcs.store') }}" class="rfcs-form" enctype="multipart/form-data">
            @csrf

            <div class="rfcs-tabs">
                <button type="button" class="rfcs-tab is-active" data-rfcs-tab="create-fiscal">Datos fiscales</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="create-address">Domicilio</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="create-certs">CSD / FIEL</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="create-branding">PDF / marca</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="create-email">Correo</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="create-complements">Complementos</button>
            </div>

            <div class="rfcs-tab-panel is-active" data-rfcs-panel="create-fiscal">
                <div class="rfcs-form-section">
                    <h3>Identidad fiscal</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>RFC</span>
                            <input type="text" name="rfc" maxlength="13" required placeholder="XAXX010101000">
                        </label>

                        <label class="span-2">
                            <span>Razón social</span>
                            <input type="text" name="razon_social" required placeholder="Razón social fiscal">
                        </label>

                        <label>
                            <span>Nombre comercial</span>
                            <input type="text" name="nombre_comercial" placeholder="Nombre visible">
                        </label>

                        <label>
                            <span>Email principal</span>
                            <input type="email" name="email" placeholder="correo@empresa.com">
                        </label>

                        <label>
                            <span>Teléfono</span>
                            <input type="text" name="telefono" placeholder="Teléfono">
                        </label>

                        <label>
                            <span>Sitio web</span>
                            <input type="text" name="sitio_web" placeholder="https://empresa.com">
                        </label>

                        <label>
                            <span>Régimen fiscal</span>
                            <select name="regimen_fiscal">
                                <option value="">Selecciona régimen fiscal</option>
                                @foreach($regimenesSat as $clave => $nombre)
                                    <option value="{{ $clave }}">{{ $clave }} · {{ $nombre }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Tipo de origen</span>
                            <select name="tipo_origen">
                                <option value="interno">Interno</option>
                                <option value="externo">Externo</option>
                            </select>
                        </label>

                        <label>
                            <span>Etiqueta</span>
                            <input type="text" name="source_label" placeholder="Matriz / Sucursal / Cliente externo">
                        </label>
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="create-address">
                <div class="rfcs-form-section">
                    <h3>Domicilio fiscal inteligente</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>Código postal</span>
                            <input type="text" name="codigo_postal" maxlength="5" placeholder="00000" data-cp-autofill>
                        </label>

                        <label>
                            <span>Estado</span>
                            <select name="estado" data-location-state>
                                <option value="">Selecciona estado</option>
                            </select>
                        </label>

                        <label>
                            <span>Municipio</span>
                            <select name="municipio" data-location-municipality>
                                <option value="">Selecciona municipio</option>
                            </select>
                        </label>

                        <label>
                            <span>Colonia</span>
                            <select name="colonia" data-location-colony>
                                <option value="">Selecciona colonia</option>
                            </select>
                        </label>

                        <label>
                            <span>Calle</span>
                            <input type="text" name="calle" placeholder="Calle">
                        </label>

                        <label>
                            <span>No. exterior</span>
                            <input type="text" name="no_exterior" placeholder="No. exterior">
                        </label>

                        <label>
                            <span>No. interior</span>
                            <input type="text" name="no_interior" placeholder="No. interior">
                        </label>
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="create-certs">
                <div class="rfcs-cert-status-grid">
                    <div class="rfcs-cert-status pending">
                        <strong>FIEL pendiente</strong>
                        <span>Carga .cer, .key y contraseña.</span>
                    </div>

                    <div class="rfcs-cert-status pending">
                        <strong>CSD pendiente</strong>
                        <span>Necesario para timbrar CFDI.</span>
                    </div>
                </div>

                <div class="rfcs-form-section">
                    <h3>FIEL / e.firma</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>FIEL .cer</span>
                            <input type="file" name="fiel_cer" accept=".cer">
                        </label>

                        <label>
                            <span>FIEL .key</span>
                            <input type="file" name="fiel_key" accept=".key">
                        </label>

                        <label>
                            <span>Contraseña FIEL</span>
                            <input type="password" name="fiel_password" placeholder="Contraseña">
                        </label>
                    </div>
                </div>

                <div class="rfcs-form-section">
                    <h3>CSD para timbrado</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>CSD .cer</span>
                            <input type="file" name="csd_cer" accept=".cer">
                        </label>

                        <label>
                            <span>CSD .key</span>
                            <input type="file" name="csd_key" accept=".key">
                        </label>

                        <label>
                            <span>Contraseña CSD</span>
                            <input type="password" name="csd_password" placeholder="Contraseña">
                        </label>

                        <label>
                            <span>Serie certificado CSD</span>
                            <input type="text" name="csd_serie" placeholder="Número de certificado">
                        </label>

                        <label>
                            <span>Vigencia hasta</span>
                            <input type="date" name="csd_vigencia_hasta">
                        </label>
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="create-branding">
                <div class="rfcs-form-section">
                    <h3>Personalización del PDF</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>Logo del RFC</span>
                            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
                        </label>

                        <label>
                            <span>Color principal</span>
                            <input type="color" name="color_pdf" value="#2563eb">
                        </label>

                        <label>
                            <span>Plantilla PDF</span>
                            <select name="plantilla_pdf">
                                <option value="moderna">Moderna</option>
                                <option value="clasica">Clásica</option>
                                <option value="compacta">Compacta</option>
                            </select>
                        </label>

                        <label class="span-2">
                            <span>Leyenda comercial / fiscal</span>
                            <input type="text" name="leyenda_pdf" placeholder="Gracias por su preferencia">
                        </label>

                        <label class="span-3">
                            <span>Notas predeterminadas para PDF</span>
                            <textarea name="notas_pdf" rows="3" placeholder="Notas visibles en la representación impresa"></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="create-email">
                <div class="rfcs-form-section">
                    <h3>Correo y envío automático</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>Correo de facturación</span>
                            <input type="email" name="correo_facturacion" placeholder="facturacion@empresa.com">
                        </label>

                        <label>
                            <span>CC predeterminado</span>
                            <input type="text" name="correo_cc" placeholder="cc@empresa.com">
                        </label>

                        <label>
                            <span>BCC predeterminado</span>
                            <input type="text" name="correo_bcc" placeholder="bcc@empresa.com">
                        </label>

                        <label class="span-3">
                            <span>Asunto predeterminado</span>
                            <input type="text" name="correo_asunto" value="Envío de CFDI">
                        </label>

                        <label class="span-3">
                            <span>Mensaje predeterminado</span>
                            <textarea name="correo_mensaje" rows="4">Adjunto encontrará su CFDI en PDF y XML.</textarea>
                        </label>
                    </div>

                    <div class="rfcs-check-grid">
                        <label><input type="checkbox" name="adjuntar_pdf" value="1" checked><span>Adjuntar PDF</span></label>
                        <label><input type="checkbox" name="adjuntar_xml" value="1" checked><span>Adjuntar XML</span></label>
                        <label><input type="checkbox" name="enviar_copia_emisor" value="1"><span>Enviar copia al emisor</span></label>
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="create-complements">
                <div class="rfcs-form-section">
                    <h3>Complementos habilitados por RFC</h3>

                    <div class="rfcs-check-grid large">
                        @foreach($complementosRfc as $value => $label)
                            <label>
                                <input type="checkbox" name="complementos[]" value="{{ $value }}">
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rfcs-modal-actions">
                <button type="button" class="rfcs-btn ghost" data-close-rfc-modal>Cancelar</button>
                <button type="submit" class="rfcs-btn primary">Guardar RFC</button>
            </div>
        </form>
    </div>
</div>

{{-- MODAL EDITAR RFC --}}
<div class="rfcs-modal" id="rfcEditModal" aria-hidden="true">
    <div class="rfcs-modal-backdrop" data-close-rfc-modal></div>

    <div class="rfcs-modal-dialog xl">
        <div class="rfcs-modal-head">
            <div>
                <h2>Editar RFC emisor</h2>
                <p>Perfil completo para que Nuevo CFDI solo seleccione el RFC y cargue todo automáticamente.</p>
            </div>

            <button type="button" class="rfcs-modal-close" data-close-rfc-modal>×</button>
        </div>

        <form method="POST" action="" class="rfcs-form" id="rfcEditForm" enctype="multipart/form-data" data-action-template="{{ route('cliente.rfcs.update', ['rfc' => '__ID__']) }}">
            @csrf
            @method('PUT')

            <div class="rfcs-tabs">
                <button type="button" class="rfcs-tab is-active" data-rfcs-tab="edit-fiscal">Datos fiscales</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="edit-address">Domicilio</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="edit-certs">CSD / FIEL</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="edit-branding">PDF / marca</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="edit-email">Correo</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="edit-complements">Complementos</button>
                <button type="button" class="rfcs-tab" data-rfcs-tab="edit-audit">Auditoría</button>
            </div>

            <div class="rfcs-tab-panel is-active" data-rfcs-panel="edit-fiscal">
                <div class="rfcs-form-section">
                    <h3>Identidad fiscal</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>RFC</span>
                            <input type="text" id="edit_rfc" readonly>
                        </label>

                        <label class="span-2">
                            <span>Razón social</span>
                            <input type="text" name="razon_social" id="edit_razon_social">
                        </label>

                        <label>
                            <span>Nombre comercial</span>
                            <input type="text" name="nombre_comercial" id="edit_nombre_comercial">
                        </label>

                        <label>
                            <span>Email principal</span>
                            <input type="email" name="email" id="edit_email">
                        </label>

                        <label>
                            <span>Teléfono</span>
                            <input type="text" name="telefono" id="edit_telefono">
                        </label>

                        <label>
                            <span>Sitio web</span>
                            <input type="text" name="sitio_web" id="edit_sitio_web">
                        </label>

                        <label>
                            <span>Régimen fiscal</span>
                            <select name="regimen_fiscal" id="edit_regimen_fiscal">
                                <option value="">Selecciona régimen fiscal</option>
                                @foreach($regimenesSat as $clave => $nombre)
                                    <option value="{{ $clave }}">{{ $clave }} · {{ $nombre }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span>Tipo de origen</span>
                            <select name="tipo_origen" id="edit_tipo_origen">
                                <option value="interno">Interno</option>
                                <option value="externo">Externo</option>
                            </select>
                        </label>

                        <label>
                            <span>Etiqueta</span>
                            <input type="text" name="source_label" id="edit_source_label">
                        </label>
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="edit-address">
                <div class="rfcs-form-section">
                    <h3>Domicilio fiscal inteligente</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>Código postal</span>
                            <input type="text" name="codigo_postal" id="edit_codigo_postal" maxlength="5" data-cp-autofill>
                        </label>

                        <label>
                            <span>Estado</span>
                            <select name="estado" id="edit_estado" data-location-state>
                                <option value="">Selecciona estado</option>
                            </select>
                        </label>

                        <label>
                            <span>Municipio</span>
                            <select name="municipio" id="edit_municipio" data-location-municipality>
                                <option value="">Selecciona municipio</option>
                            </select>
                        </label>

                        <label>
                            <span>Colonia</span>
                            <select name="colonia" id="edit_colonia" data-location-colony>
                                <option value="">Selecciona colonia</option>
                            </select>
                        </label>

                        <label>
                            <span>Calle</span>
                            <input type="text" name="calle" id="edit_calle">
                        </label>

                        <label>
                            <span>No. exterior</span>
                            <input type="text" name="no_exterior" id="edit_no_exterior">
                        </label>

                        <label>
                            <span>No. interior</span>
                            <input type="text" name="no_interior" id="edit_no_interior">
                        </label>
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="edit-certs">
                <div class="rfcs-cert-status-grid">
                    <div class="rfcs-cert-status" id="edit_fiel_status_box">
                        <strong id="edit_fiel_status">FIEL pendiente</strong>
                        <span id="edit_fiel_detail">Carga .cer, .key y contraseña.</span>
                    </div>

                    <div class="rfcs-cert-status" id="edit_csd_status_box">
                        <strong id="edit_csd_status">CSD pendiente</strong>
                        <span id="edit_csd_detail">Necesario para timbrar CFDI.</span>
                    </div>
                </div>

                <div class="rfcs-form-section">
                    <h3>FIEL / e.firma</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>FIEL .cer</span>
                            <input type="file" name="fiel_cer" accept=".cer">
                        </label>

                        <label>
                            <span>FIEL .key</span>
                            <input type="file" name="fiel_key" accept=".key">
                        </label>

                        <label>
                            <span>Contraseña FIEL</span>
                            <input type="password" name="fiel_password" placeholder="Actualizar contraseña">
                        </label>
                    </div>
                </div>

                <div class="rfcs-form-section">
                    <h3>CSD para timbrado</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>CSD .cer</span>
                            <input type="file" name="csd_cer" accept=".cer">
                        </label>

                        <label>
                            <span>CSD .key</span>
                            <input type="file" name="csd_key" accept=".key">
                        </label>

                        <label>
                            <span>Contraseña CSD</span>
                            <input type="password" name="csd_password" placeholder="Actualizar contraseña">
                        </label>

                        <label>
                            <span>Serie certificado CSD</span>
                            <input type="text" name="csd_serie" id="edit_csd_serie">
                        </label>

                        <label>
                            <span>Vigencia hasta</span>
                            <input type="date" name="csd_vigencia_hasta" id="edit_csd_vigencia_hasta">
                        </label>
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="edit-branding">
                <div class="rfcs-form-section">
                    <h3>Personalización del PDF</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>Logo del RFC</span>
                            <input type="file" name="logo" accept=".jpg,.jpeg,.png,.webp">
                        </label>

                        <label>
                            <span>Color principal</span>
                            <input type="color" name="color_pdf" id="edit_color_pdf" value="#2563eb">
                        </label>

                        <label>
                            <span>Plantilla PDF</span>
                            <select name="plantilla_pdf" id="edit_plantilla_pdf">
                                <option value="moderna">Moderna</option>
                                <option value="clasica">Clásica</option>
                                <option value="compacta">Compacta</option>
                            </select>
                        </label>

                        <label class="span-2">
                            <span>Leyenda comercial / fiscal</span>
                            <input type="text" name="leyenda_pdf" id="edit_leyenda_pdf">
                        </label>

                        <label class="span-3">
                            <span>Notas predeterminadas para PDF</span>
                            <textarea name="notas_pdf" id="edit_notas_pdf" rows="3"></textarea>
                        </label>
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="edit-email">
                <div class="rfcs-form-section">
                    <h3>Correo y envío automático</h3>

                    <div class="rfcs-form-grid three">
                        <label>
                            <span>Correo de facturación</span>
                            <input type="email" name="correo_facturacion" id="edit_correo_facturacion">
                        </label>

                        <label>
                            <span>CC predeterminado</span>
                            <input type="text" name="correo_cc" id="edit_correo_cc">
                        </label>

                        <label>
                            <span>BCC predeterminado</span>
                            <input type="text" name="correo_bcc" id="edit_correo_bcc">
                        </label>

                        <label class="span-3">
                            <span>Asunto predeterminado</span>
                            <input type="text" name="correo_asunto" id="edit_correo_asunto">
                        </label>

                        <label class="span-3">
                            <span>Mensaje predeterminado</span>
                            <textarea name="correo_mensaje" id="edit_correo_mensaje" rows="4"></textarea>
                        </label>
                    </div>

                    <div class="rfcs-check-grid">
                        <label><input type="checkbox" name="adjuntar_pdf" id="edit_adjuntar_pdf" value="1"><span>Adjuntar PDF</span></label>
                        <label><input type="checkbox" name="adjuntar_xml" id="edit_adjuntar_xml" value="1"><span>Adjuntar XML</span></label>
                        <label><input type="checkbox" name="enviar_copia_emisor" id="edit_enviar_copia_emisor" value="1"><span>Enviar copia al emisor</span></label>
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="edit-complements">
                <div class="rfcs-form-section">
                    <h3>Complementos habilitados por RFC</h3>

                    <div class="rfcs-check-grid large">
                        @foreach($complementosRfc as $value => $label)
                            <label>
                                <input type="checkbox" name="complementos[]" value="{{ $value }}" data-edit-complemento>
                                <span>{{ $label }}</span>
                            </label>
                        @endforeach
                    </div>
                </div>
            </div>

            <div class="rfcs-tab-panel" data-rfcs-panel="edit-audit">
                <div class="rfcs-form-section">
                    <h3>Auditoría y salud fiscal</h3>

                    <div class="rfcs-health-grid">
                        <div><strong id="audit_rfc_status">RFC activo</strong><span>Estado operativo del emisor.</span></div>
                        <div><strong id="audit_fiel_status">FIEL pendiente</strong><span>Recomendado para operaciones SAT.</span></div>
                        <div><strong id="audit_csd_status">CSD pendiente</strong><span>Necesario para timbrar CFDI.</span></div>
                        <div><strong id="audit_series_status">Series pendientes</strong><span>Necesarias para folios automáticos.</span></div>
                        <div><strong id="audit_logo_status">Logo pendiente</strong><span>Personalización del PDF.</span></div>
                        <div><strong id="audit_email_status">Correo pendiente</strong><span>Envío automático al receptor.</span></div>
                        <div><strong id="audit_updated_at">Sin actualización</strong><span>Última actualización del perfil.</span></div>
                    </div>
                </div>
            </div>

            <div class="rfcs-modal-actions">
                <button type="button" class="rfcs-btn ghost" data-close-rfc-modal>Cancelar</button>
                <button type="submit" class="rfcs-btn primary">Guardar cambios</button>
            </div>
        </form>
    </div>
</div>