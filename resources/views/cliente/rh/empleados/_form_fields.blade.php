@php
    $empleado = $empleado ?? null;
    $catalogos = $catalogos ?? [];

    $value = function (string $key, $default = '') use ($empleado) {
        return old($key, data_get($empleado, $key, $default));
    };

    $dateValue = function (string $key) use ($empleado) {
        $old = old($key);
        if ($old) {
            return $old;
        }

        $raw = data_get($empleado, $key);
        if (!$raw) {
            return '';
        }

        try {
            return \Carbon\Carbon::parse($raw)->format('Y-m-d');
        } catch (\Throwable $e) {
            return '';
        }
    };

    $options = function (string $key) use ($catalogos): array {
        return is_array($catalogos[$key] ?? null) ? $catalogos[$key] : [];
    };
@endphp

<div class="rh360-modal-layout">
    <div class="rh360-modal-main">

        <section class="rh360-form-section is-open">
            <div class="rh360-section-head">
                <span>01</span>
                <div>
                    <strong>Identidad fiscal</strong>
                    <small>Datos obligatorios del receptor para CFDI Nómina 4.0</small>
                </div>
            </div>

            <div class="rh360-form-grid">
                <label class="rh360-field">
                    <span>Número empleado <b>Obligatorio</b></span>
                    <input name="numero_empleado" value="{{ $value('numero_empleado') }}" required placeholder="EMP-001" data-rh-ai="numero_empleado">
                </label>

                <label class="rh360-field">
                    <span>RFC <b>CFDI</b></span>
                    <input name="rfc" value="{{ $value('rfc') }}" maxlength="13" required placeholder="XAXX010101000" data-rh-ai="rfc">
                </label>

                <label class="rh360-field">
                    <span>CURP <b>Nómina</b></span>
                    <input name="curp" value="{{ $value('curp') }}" maxlength="18" required placeholder="18 caracteres" data-rh-ai="curp">
                </label>

                <label class="rh360-field">
                    <span>NSS <em>IMSS</em></span>
                    <input name="nss" value="{{ $value('nss') }}" maxlength="11" placeholder="11 dígitos" data-rh-ai="nss">
                </label>

                <label class="rh360-field">
                    <span>Nombre <b>CFDI</b></span>
                    <input name="nombre" value="{{ $value('nombre') }}" required placeholder="Nombre" data-rh-ai="nombre">
                </label>

                <label class="rh360-field">
                    <span>Apellido paterno <b>CFDI</b></span>
                    <input name="apellido_paterno" value="{{ $value('apellido_paterno') }}" required placeholder="Apellido paterno" data-rh-ai="apellido_paterno">
                </label>

                <label class="rh360-field">
                    <span>Apellido materno</span>
                    <input name="apellido_materno" value="{{ $value('apellido_materno') }}" placeholder="Apellido materno">
                </label>

                <label class="rh360-field">
                    <span>CP fiscal <b>CFDI 4.0</b></span>
                    <input name="codigo_postal" value="{{ $value('codigo_postal') }}" maxlength="5" required placeholder="00000" data-rh-ai="codigo_postal">
                </label>

                <label class="rh360-field">
                    <span>Régimen fiscal <b>Catálogo SAT</b></span>
                    <select name="regimen_fiscal" required data-rh-ai="regimen_fiscal">
                        @foreach($options('regimenes_fiscales') as $key => $label)
                            <option value="{{ $key }}" @selected((string) $value('regimen_fiscal', '605') === (string) $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="rh360-field">
                    <span>Uso CFDI <b>Catálogo SAT</b></span>
                    <select name="uso_cfdi" required data-rh-ai="uso_cfdi">
                        @foreach($options('usos_cfdi') as $key => $label)
                            <option value="{{ $key }}" @selected((string) $value('uso_cfdi', 'CN01') === (string) $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>
            </div>
        </section>

        <section class="rh360-form-section is-open">
            <div class="rh360-section-head">
                <span>02</span>
                <div>
                    <strong>Datos laborales SAT</strong>
                    <small>Catálogos oficiales para complemento Nómina 1.2</small>
                </div>
            </div>

            <div class="rh360-form-grid">
                <label class="rh360-field">
                    <span>Fecha inicio laboral <em>Condicional</em></span>
                    <input type="date" name="fecha_inicio_relacion_laboral" value="{{ $dateValue('fecha_inicio_relacion_laboral') }}" data-rh-ai="fecha_inicio_relacion_laboral">
                </label>

                <label class="rh360-field">
                    <span>Tipo contrato <b>Catálogo SAT</b></span>
                    <select name="tipo_contrato" required data-rh-ai="tipo_contrato">
                        <option value="">Selecciona...</option>
                        @foreach($options('tipos_contrato') as $key => $label)
                            <option value="{{ $key }}" @selected((string) $value('tipo_contrato') === (string) $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="rh360-field">
                    <span>Tipo jornada <em>Catálogo SAT</em></span>
                    <select name="tipo_jornada" data-rh-ai="tipo_jornada">
                        <option value="">Selecciona...</option>
                        @foreach($options('tipos_jornada') as $key => $label)
                            <option value="{{ $key }}" @selected((string) $value('tipo_jornada') === (string) $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="rh360-field">
                    <span>Tipo régimen nómina <b>Catálogo SAT</b></span>
                    <select name="tipo_regimen" required data-rh-ai="tipo_regimen">
                        <option value="">Selecciona...</option>
                        @foreach($options('tipos_regimen') as $key => $label)
                            <option value="{{ $key }}" @selected((string) $value('tipo_regimen') === (string) $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="rh360-field">
                    <span>Periodicidad pago <b>Catálogo SAT</b></span>
                    <select name="periodicidad_pago" required data-rh-ai="periodicidad_pago">
                        <option value="">Selecciona...</option>
                        @foreach($options('periodicidades_pago') as $key => $label)
                            <option value="{{ $key }}" @selected((string) $value('periodicidad_pago') === (string) $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="rh360-field">
                    <span>Departamento</span>
                    <input name="departamento" value="{{ $value('departamento') }}" placeholder="Administración">
                </label>

                <label class="rh360-field">
                    <span>Puesto</span>
                    <input name="puesto" value="{{ $value('puesto') }}" placeholder="Auxiliar">
                </label>

                <label class="rh360-field">
                    <span>Riesgo puesto <em>IMSS</em></span>
                    <select name="riesgo_puesto" data-rh-ai="riesgo_puesto">
                        <option value="">Selecciona...</option>
                        @foreach($options('riesgos_puesto') as $key => $label)
                            <option value="{{ $key }}" @selected((string) $value('riesgo_puesto') === (string) $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="rh360-field">
                    <span>Antigüedad</span>
                    <input name="antiguedad" value="{{ $value('antiguedad') }}" placeholder="P1W / P1Y">
                </label>
            </div>
        </section>

        <section class="rh360-form-section is-open">
            <div class="rh360-section-head">
                <span>03</span>
                <div>
                    <strong>Pago y banco</strong>
                    <small>Información para dispersión, IMSS y cálculos internos</small>
                </div>
            </div>

            <div class="rh360-form-grid">
                <label class="rh360-field">
                    <span>Salario base cotización <em>IMSS</em></span>
                    <input type="number" step="0.01" min="0" name="salario_base_cot_apor" value="{{ $value('salario_base_cot_apor', 0) }}">
                </label>

                <label class="rh360-field">
                    <span>Salario diario integrado <em>IMSS</em></span>
                    <input type="number" step="0.01" min="0" name="salario_diario_integrado" value="{{ $value('salario_diario_integrado', 0) }}">
                </label>

                <label class="rh360-field">
                    <span>Banco <em>Catálogo</em></span>
                    <select name="banco" data-rh-ai="banco">
                        <option value="">Selecciona...</option>
                        @foreach($options('bancos') as $key => $label)
                            <option value="{{ $key }}" @selected((string) $value('banco') === (string) $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="rh360-field">
                    <span>Cuenta bancaria / CLABE</span>
                    <input name="cuenta_bancaria" value="{{ $value('cuenta_bancaria') }}" maxlength="30" placeholder="18 dígitos">
                </label>
            </div>
        </section>

        <section class="rh360-form-section is-open">
            <div class="rh360-section-head">
                <span>04</span>
                <div>
                    <strong>Contacto y control interno</strong>
                    <small>Datos operativos para Recursos Humanos</small>
                </div>
            </div>

            <div class="rh360-form-grid">
                <label class="rh360-field">
                    <span>Email</span>
                    <input type="email" name="email" value="{{ $value('email') }}" placeholder="empleado@empresa.com">
                </label>

                <label class="rh360-field">
                    <span>Teléfono</span>
                    <input name="telefono" value="{{ $value('telefono') }}" placeholder="Teléfono">
                </label>

                <label class="rh360-check">
                    <input type="hidden" name="sindicalizado" value="0">
                    <input type="checkbox" name="sindicalizado" value="1" @checked((bool) $value('sindicalizado', false))>
                    <span>Sindicalizado</span>
                </label>

                <label class="rh360-check">
                    <input type="hidden" name="activo" value="0">
                    <input type="checkbox" name="activo" value="1" @checked((bool) $value('activo', true))>
                    <span>Empleado activo</span>
                </label>
            </div>
        </section>

                <section class="rh360-form-section is-open">
            <div class="rh360-section-head">
                <span>05</span>
                <div>
                    <strong>Control de asistencia</strong>
                    <small>Preparado para WhatsApp, captura interna o conexión con biométrico externo</small>
                </div>
            </div>

            <div class="rh360-form-grid">
                <label class="rh360-field">
                    <span>Método de asistencia <b>Operativo</b></span>
                    <select name="metodo_asistencia" data-rh-ai="metodo_asistencia">
                        @foreach($options('metodos_asistencia') as $key => $label)
                            <option value="{{ $key }}" @selected((string) $value('metodo_asistencia', 'manual') === (string) $key)>
                                {{ $label }}
                            </option>
                        @endforeach
                    </select>
                </label>

                <label class="rh360-field">
                    <span>Código biométrico <em>Opcional</em></span>
                    <input
                        name="codigo_biometrico"
                        value="{{ $value('codigo_biometrico') }}"
                        maxlength="80"
                        placeholder="ID del reloj checador / biométrico"
                        data-rh-ai="codigo_biometrico"
                    >
                </label>

                <label class="rh360-field">
                    <span>Dispositivo biométrico <em>Opcional</em></span>
                    <input
                        name="dispositivo_biometrico"
                        value="{{ $value('dispositivo_biometrico') }}"
                        maxlength="120"
                        placeholder="Sucursal, equipo o nombre del dispositivo"
                        data-rh-ai="dispositivo_biometrico"
                    >
                </label>

                <label class="rh360-field">
                    <span>PIN de asistencia <em>Opcional</em></span>
                    <input
                        name="pin_asistencia"
                        value="{{ $value('pin_asistencia') }}"
                        maxlength="20"
                        placeholder="PIN interno o código rápido"
                        data-rh-ai="pin_asistencia"
                    >
                </label>

                <label class="rh360-field">
                    <span>WhatsApp asistencia <b>Futuro módulo</b></span>
                    <input
                        name="telefono_whatsapp"
                        value="{{ $value('telefono_whatsapp') }}"
                        maxlength="30"
                        placeholder="5215512345678"
                        data-rh-ai="telefono_whatsapp"
                    >
                </label>

                <label class="rh360-check">
                    <input type="hidden" name="sincronizar_asistencia" value="0">
                    <input
                        type="checkbox"
                        name="sincronizar_asistencia"
                        value="1"
                        @checked((bool) $value('sincronizar_asistencia', false))
                    >
                    <span>Sincronizar asistencia</span>
                </label>
            </div>

            <div class="rh360-assist-note">
                Este registro deja preparado al empleado para el módulo de asistencias. Podrá usarse con registro manual, WhatsApp o integración futura con biométricos externos mediante código de empleado/dispositivo.
            </div>
        </section>
    </div>

    <aside class="rh360-ai-card">
        <div class="rh360-ai-badge">IA RH / Fiscal</div>
        <h3>Checklist CFDI Nómina</h3>
        <p>Valida RFC, CURP, CP fiscal y catálogos SAT antes de usar este empleado en un CFDI tipo N.</p>

        <div class="rh360-ai-score">
            <strong data-rh-ai-score>0%</strong>
            <span data-rh-ai-level>Completa datos críticos</span>
        </div>

        <ul class="rh360-ai-list" data-rh-ai-list></ul>

        <div class="rh360-ai-note">
            La revisión IA detecta datos mínimos para CFDI Nómina 4.0: RFC, CURP, CP fiscal, régimen 605, uso CFDI CN01, tipo contrato, tipo régimen y periodicidad.
        </div>
    </aside>
</div>