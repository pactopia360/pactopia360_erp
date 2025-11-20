{{-- resources/views/cliente/perfil/settings.blade.php
     Configuración de la cuenta · Pactopia360 (FREE vs PRO)
--}}
@extends('layouts.cliente')
@section('title','Configuración de la cuenta · Pactopia360')

@php
  use Illuminate\Support\Facades\Route;
  use App\Http\Controllers\Cliente\HomeController as ClientHome;

  $user   = $user   ?? auth('web')->user();
  $cuenta = $cuenta ?? ($user?->cuenta ?? null);

  // ===== Resumen unificado de cuenta (mismo helper que en header / home / CFDI) =====
  $summary = $summary ?? app(ClientHome::class)->buildAccountSummary();

  $planRaw   = strtoupper((string)($summary['plan'] ?? ($cuenta->plan_actual ?? $cuenta->plan ?? 'FREE')));
  $planSlug  = strtolower($planRaw);
  $isProPlan = (bool)($summary['is_pro'] ?? in_array($planSlug, ['pro','premium','empresa','business'], true));
  $planLabel = $planRaw;

  $rtPerfil      = Route::has('cliente.perfil')        ? route('cliente.perfil')        : '#';
  $rtEstadoCta   = Route::has('cliente.estado_cuenta') ? route('cliente.estado_cuenta') : '#';
  $rtHome        = Route::has('cliente.home')          ? route('cliente.home')          : '#';
  $rtFacturacion = Route::has('cliente.facturacion.index') ? route('cliente.facturacion.index') : '#';
  $rtExportMes   = Route::has('cliente.facturacion.export') ? route('cliente.facturacion.export',['month'=>now()->format('Y-m')]) : '#';
  $rtVerifyPhone = Route::has('cliente.verify.phone')  ? route('cliente.verify.phone')  : '#';
  $rtLogout      = Route::has('cliente.logout')        ? route('cliente.logout')        : '#';
  $rtBilling     = Route::has('cliente.billing.statement') ? route('cliente.billing.statement') : $rtEstadoCta;
  $rtSoporte     = Route::has('cliente.soporte')       ? route('cliente.soporte')       : '#';

  // Upgrade: usamos registro PRO o marketplace como fallback
  $rtUpgrade = Route::has('cliente.registro.pro')
    ? route('cliente.registro.pro')
    : (Route::has('cliente.marketplace') ? route('cliente.marketplace') : '#');

  $nombreCuenta = $cuenta?->razon_social
    ?? $user?->nombre
    ?? $user?->email
    ?? 'Tu cuenta Pactopia360';

  $telefono = $user?->telefono ?? $user?->phone ?? '';
  $hasPhone = trim((string)$telefono) !== '';
@endphp

@push('styles')
<style>
  .settings-page{
    font-family:'Poppins',system-ui,sans-serif;
    display:flex;
    flex-direction:column;
    gap:16px;
    padding-bottom:32px;
  }

  .settings-head{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:14px;
    flex-wrap:wrap;
  }
  .settings-title{
    margin:0;
    font:900 22px/1.2 'Poppins';
    color:#0f172a;
  }
  .settings-sub{
    font-size:13px;
    color:#6b7280;
    margin-top:4px;
  }

  .plan-pill{
    display:inline-flex;
    align-items:center;
    gap:8px;
    border-radius:999px;
    padding:7px 13px;
    font:800 11px/1 'Poppins';
    letter-spacing:.14em;
    text-transform:uppercase;
    border:1px solid #fee2e2;
    background:#fef2f2;
    color:#b91c1c;
  }
  .plan-pill span[aria-hidden="true"]{font-size:14px;}
  .plan-pill.pro{
    border-color:#bbf7d0;
    background:#dcfce7;
    color:#166534;
  }

  .btn-upgrade{
    display:inline-flex;
    align-items:center;
    gap:8px;
    padding:9px 14px;
    border-radius:999px;
    border:0;
    text-decoration:none;
    cursor:pointer;
    font:800 13px/1 'Poppins';
    background:linear-gradient(90deg,#e11d48,#be123c);
    color:#fff;
    box-shadow:0 10px 26px rgba(225,29,72,.25);
  }
  .btn-upgrade span[aria-hidden="true"]{font-size:14px;}

  .settings-grid{
    display:grid;
    gap:18px;
    align-items:flex-start;
  }
  @media(min-width:1080px){
    .settings-grid{
      grid-template-columns: minmax(0,1.25fr) minmax(0,1fr);
    }
  }

  .card-settings{
    border-radius:18px;
    border:1px solid #f3d5dc;
    background:linear-gradient(180deg,rgba(255,255,255,.96),rgba(255,255,255,.9));
    padding:18px 20px 20px;
    box-shadow:0 8px 26px rgba(225,29,72,.06);
    display:flex;
    flex-direction:column;
    gap:12px;
  }
  .card-settings h2{
    margin:0;
    font:800 13px/1.2 'Poppins';
    text-transform:uppercase;
    letter-spacing:.16em;
    color:#e11d48;
  }
  .card-sub{
    font-size:12px;
    color:#6b7280;
  }

  .fields-grid{
    display:grid;
    gap:10px 16px;
  }
  @media(min-width:900px){
    .fields-grid.cols-2{grid-template-columns:repeat(2,minmax(0,1fr));}
  }

  .field{
    display:flex;
    flex-direction:column;
    gap:4px;
  }
  .field-label{
    font:800 11px/1.1 'Poppins';
    letter-spacing:.12em;
    text-transform:uppercase;
    color:#6b7280;
  }
  .field-text{
    font-size:13px;
    font-weight:700;
    color:#0f172a;
  }
  .field-help{
    font-size:11px;
    color:#9ca3af;
  }

  .input-settings,
  .select-settings{
    border-radius:11px;
    border:1px solid #e5e7eb;
    background:#fff;
    padding:9px 11px;
    font:600 13px/1.2 'Poppins';
    color:#0f172a;
  }
  .input-settings:focus,
  .select-settings:focus{
    outline:none;
    border-color:#e11d48;
    box-shadow:0 0 0 1px rgba(225,29,72,.18);
  }

  .btn-secondary{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:8px 13px;
    border-radius:999px;
    border:1px solid #f3d5dc;
    background:#fff;
    font:800 12px/1 'Poppins';
    color:#e11d48;
    cursor:pointer;
    text-decoration:none;
  }
  .btn-secondary:hover{
    background:#fff0f3;
  }

  .btn-primary{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    padding:9px 14px;
    border-radius:999px;
    border:0;
    cursor:pointer;
    font:800 13px/1 'Poppins';
    background:linear-gradient(90deg,#e11d48,#be123c);
    color:#fff;
    box-shadow:0 10px 26px rgba(225,29,72,.25);
  }
  .btn-primary span[aria-hidden="true"]{font-size:14px;}

  .btn-ghost-small{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:4px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid #e5e7eb;
    background:#fff;
    font:700 11px/1 'Poppins';
    cursor:pointer;
    text-decoration:none;
    color:#0f172a;
  }
  .btn-ghost-small:hover{background:#f9fafb;}

  .switch-row{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
  }
  .switch-label{
    font-size:12px;
    font-weight:600;
    color:#374151;
  }
  .switch-description{
    font-size:11px;
    color:#9ca3af;
    margin-top:2px;
  }
  .toggle{
    width:44px;
    height:24px;
    border-radius:999px;
    border:1px solid #e5e7eb;
    background:#e5e7eb;
    padding:2px;
    display:flex;
    align-items:center;
  }
  .toggle-dot{
    width:18px;height:18px;border-radius:999px;
    background:#fff;
    box-shadow:0 1px 3px rgba(15,23,42,.24);
    transform:translateX(0);
    transition:transform .16s ease-out, background .16s, box-shadow .16s;
  }
  .toggle.on{background:#22c55e;border-color:#16a34a;}
  .toggle.on .toggle-dot{transform:translateX(18px);}

  .badge-mini{
    display:inline-flex;
    align-items:center;
    gap:4px;
    padding:3px 7px;
    border-radius:999px;
    background:#f3f4f6;
    font-size:11px;
    font-weight:700;
    color:#4b5563;
  }
  .badge-mini.pro{
    background:#dcfce7;
    color:#166534;
  }
  .badge-mini.free{
    background:#fee2e2;
    color:#b91c1c;
  }

  .lock-note{
    font-size:11px;
    color:#b91c1c;
    font-weight:600;
  }
  .locked,
  .locked:hover{
    opacity:.6;
    cursor:not-allowed;
    filter:grayscale(.2);
  }

  .danger-zone{
    background:#fef2f2;
    border-color:#fecaca;
  }
  .danger-zone h2{color:#b91c1c;}
  .danger-text{
    font-size:12px;
    color:#7f1d1d;
  }

  @media(max-width:768px){
    .settings-title{font-size:19px;}
  }
</style>
@endpush

@section('content')
<div class="settings-page">

  {{-- HEADER CONFIGURACIÓN --}}
  <div class="settings-head">
    <div>
      <h1 class="settings-title">Configuración de la cuenta</h1>
      <div class="settings-sub">
        Administra tu perfil, seguridad, preferencias y módulos de facturación desde un solo lugar.
      </div>
    </div>

    <div style="display:flex;flex-direction:column;align-items:flex-end;gap:8px;">
      <div class="plan-pill {{ $isProPlan ? 'pro' : '' }}">
        <span aria-hidden="true">{{ $isProPlan ? '⭐' : '🆓' }}</span>
        <span>{{ $planLabel }}</span>
      </div>

      @if(!$isProPlan && $rtUpgrade !== '#')
        <a href="{{ $rtUpgrade }}" class="btn-upgrade">
          <span aria-hidden="true">🚀</span>
          <span>Actualizar a PRO</span>
        </a>
      @endif
    </div>
  </div>

  {{-- FLASHES --}}
  @if(session('ok'))
    <div class="card-settings" style="border-color:#bbf7d0;background:#ecfdf5;color:#047857;">
      <strong>{{ session('ok') }}</strong>
    </div>
  @endif
  @if($errors->any())
    <div class="card-settings" style="border-color:#fecaca;background:#fef2f2;color:#b91c1c;">
      <strong>{{ $errors->first() }}</strong>
    </div>
  @endif

  {{-- GRID PRINCIPAL --}}
  <div class="settings-grid">

    {{-- COLUMNA IZQUIERDA --}}
    <div class="settings-col-left" style="display:flex;flex-direction:column;gap:18px;">

      {{-- PERFIL Y DATOS --}}
      <section class="card-settings">
        <h2>Perfil y datos de la cuenta</h2>
        <div class="card-sub">
          Define cómo se identifica tu cuenta dentro de Pactopia360.
        </div>

        <div class="fields-grid cols-2">
          <div class="field">
            <div class="field-label">Nombre del usuario</div>
            <div class="field-text">{{ $user?->nombre ?? $user?->name ?? '—' }}</div>
            <div class="field-help">Este nombre se usa en la barra superior y notificaciones.</div>
          </div>

          <div class="field">
            <div class="field-label">Correo principal</div>
            <div class="field-text">{{ $user?->email ?? '—' }}</div>
            <div class="field-help">Correo principal para iniciar sesión y recibir avisos.</div>
          </div>

          <div class="field">
            <div class="field-label">Razón social / nombre comercial</div>
            <div class="field-text">{{ $cuenta?->razon_social ?? $cuenta?->nombre_comercial ?? $nombreCuenta }}</div>
          </div>

          <div class="field">
            <div class="field-label">RFC principal</div>
            <div class="field-text">{{ $cuenta?->rfc ?? '—' }}</div>
            <div class="field-help">RFC principal de facturación en esta cuenta.</div>
          </div>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:6px;">
          <a href="{{ $rtPerfil }}" class="btn-secondary">
            <span aria-hidden="true">✏️</span> <span>Editar perfil completo</span>
          </a>
          <a href="{{ $rtEstadoCta }}" class="btn-secondary">
            <span aria-hidden="true">📊</span> <span>Ver estado de cuenta</span>
          </a>
        </div>
      </section>

      {{-- SEGURIDAD --}}
      <section class="card-settings">
        <h2>Seguridad</h2>
        <div class="card-sub">
          Mantén segura tu cuenta cambiando la contraseña y manteniendo tu teléfono actualizado.
        </div>

        {{-- Cambiar contraseña --}}
        <form method="POST" action="{{ route('cliente.perfil.password.update') }}">
          @csrf
          @method('PUT')

          <div class="fields-grid cols-2" style="margin-top:6px;">
            <div class="field">
              <label class="field-label" for="current_password">Contraseña actual</label>
              <input id="current_password" name="current_password" type="password"
                     class="input-settings" autocomplete="current-password">
            </div>
            <div class="field">
              <label class="field-label" for="password">Nueva contraseña</label>
              <input id="password" name="password" type="password"
                     class="input-settings" autocomplete="new-password">
              <div class="field-help">Mínimo 8 caracteres. Usa mayúsculas, minúsculas y números.</div>
            </div>
            <div class="field">
              <label class="field-label" for="password_confirmation">Confirmar nueva contraseña</label>
              <input id="password_confirmation" name="password_confirmation" type="password"
                     class="input-settings" autocomplete="new-password">
            </div>
          </div>

          <button type="submit" class="btn-primary" style="margin-top:10px;">
            <span aria-hidden="true">🔐</span>
            <span>Actualizar contraseña</span>
          </button>
        </form>

        {{-- Teléfono --}}
        <form method="POST" action="{{ route('cliente.perfil.phone.update') }}" style="margin-top:14px;">
          @csrf
          @method('PUT')

          <div class="fields-grid cols-2">
            <div class="field">
              <label class="field-label" for="phone">Teléfono móvil</label>
              <input id="phone" name="phone" type="tel"
                     class="input-settings"
                     value="{{ old('phone', $telefono) }}">
              <div class="field-help">
                Se utiliza para alertas críticas y recuperación de acceso.
              </div>
            </div>

            <div class="field">
              <div class="field-label">Verificación por SMS</div>
              <div class="field-text">
                @if($hasPhone)
                  {{ $isProPlan ? 'Teléfono pendiente de verificar' : 'Sin teléfono verificado' }}
                @else
                  Sin teléfono capturado
                @endif
              </div>
              <div class="field-help">
                @if($hasPhone)
                  Verifica tu teléfono para fortalecer la seguridad de tu cuenta.
                @else
                  Agrega un número de celular y guárdalo para poder verificarlo.
                @endif
              </div>

              <div style="margin-top:6px;display:flex;flex-wrap:wrap;gap:8px;">
                <a href="{{ $rtVerifyPhone }}" class="btn-ghost-small">
                  <span aria-hidden="true">📲</span>
                  <span>{{ $hasPhone ? 'Ir a verificación con código SMS' : 'Configurar verificación por SMS' }}</span>
                </a>
              </div>
            </div>
          </div>

          <button type="submit" class="btn-secondary" style="margin-top:8px;">
            <span aria-hidden="true">📱</span>
            <span>Guardar teléfono</span>
          </button>
        </form>
      </section>

      {{-- FACTURACIÓN Y SAT --}}
      <section class="card-settings">
        <h2>Facturación y SAT</h2>
        <div class="card-sub">
          Accesos directos para configurar emisores y revisar tu actividad de facturación.
        </div>

        <div class="fields-grid cols-2">
          <div class="field">
            <div class="field-label">Emisores y receptores</div>
            <div class="field-text">Administrar tus emisores, logotipos y clientes desde tu perfil / dashboard.</div>
            <div class="field-help">
              @if($isProPlan)
                Acceso completo a múltiples RFC emisores.
              @else
                En FREE se limita el número de emisores disponibles.
              @endif
            </div>
          </div>

          <div class="field">
            <div class="field-label">CFDI y actividad</div>
            <div class="field-text">
              @if($isProPlan)
                Revisa tu CFDI emitido, exporta a CSV y revisa gráficos de desempeño.
              @else
                Emite CFDI básicos. Exportación y algunas métricas se habilitan en PRO.
              @endif
            </div>
          </div>
        </div>

        <div style="display:flex;flex-wrap:wrap;gap:8px;margin-top:6px;">
          {{-- Gestionar emisores: disponible para PRO, limitado visualmente en FREE --}}
          <a href="{{ $rtPerfil }}#emisores"
             class="btn-secondary {{ $isProPlan ? '' : 'locked' }}"
             @if(!$isProPlan) aria-disabled="true" title="Gestión avanzada de emisores disponible en PRO" @endif>
            <span aria-hidden="true">🏢</span>
            <span>Gestionar emisores</span>
          </a>

          <a href="{{ $rtFacturacion }}"
             class="btn-secondary {{ $isProPlan ? '' : '' }}">
            <span aria-hidden="true">🧾</span>
            <span>Ir a facturación</span>
          </a>

          <a href="{{ $rtHome }}" class="btn-secondary">
            <span aria-hidden="true">📈</span>
            <span>Ver dashboard</span>
          </a>
        </div>

        <hr style="border:none;border-top:1px dashed #f3d5dc;margin:12px 0 8px;">

        <div class="fields-grid cols-2">
          <div class="field">
            <div class="field-label">Preferencias de CFDI (local)</div>
            <div class="field-help">Se guardan sólo en este navegador para agilizar la captura de nuevos CFDI.</div>
          </div>
        </div>

        <div class="fields-grid cols-2" style="margin-top:4px;">
          <div class="field">
            <label class="field-label" for="def_uso_cfdi">Uso CFDI por defecto</label>
            <input id="def_uso_cfdi" class="input-settings" value="G03" readonly>
          </div>
          <div class="field">
            <label class="field-label" for="def_metodo_pago">Método de pago por defecto</label>
            <input id="def_metodo_pago" class="input-settings" value="PUE" readonly>
          </div>
        </div>

        <button type="button" class="btn-secondary" style="margin-top:8px;">
          <span aria-hidden="true">💾</span>
          <span>Guardar preferencias locales</span>
        </button>
      </section>

    </div>{{-- /col izquierda --}}

    {{-- COLUMNA DERECHA --}}
    <div class="settings-col-right" style="display:flex;flex-direction:column;gap:18px;">

      {{-- APARIENCIA Y PREFERENCIAS --}}
      <section class="card-settings">
        <h2>Apariencia y preferencias</h2>
        <div class="card-sub">Personaliza cómo ves Pactopia360 en este equipo.</div>

        <div class="fields-grid">
          <div class="field">
            <div class="field-label">Tema y diseño</div>
            <div class="switch-row">
              <div>
                <div class="switch-label">Tema oscuro</div>
                <div class="switch-description">
                  Cambia entre tema claro y oscuro. Se sincroniza con el botón del encabezado.
                </div>
              </div>
              <div class="toggle" data-local-toggle="theme-dark">
                <div class="toggle-dot"></div>
              </div>
            </div>
          </div>

          <div class="fields-grid cols-2" style="margin-top:8px;">
            <div class="field">
              <label class="field-label" for="ui_lang">Idioma (solo interfaz)</label>
              <select id="ui_lang" class="select-settings">
                <option value="es-MX">Español (MX)</option>
                <option value="es-ES">Español (ES)</option>
                <option value="en-US">English</option>
              </select>
              <div class="field-help">Por ahora afecta solo formatos de fecha/número locales del navegador.</div>
            </div>
            <div class="field">
              <label class="field-label" for="ui_tz">Zona horaria</label>
              <select id="ui_tz" class="select-settings">
                <option value="America/Mexico_City">America/Mexico_City</option>
                <option value="America/Monterrey">America/Monterrey</option>
                <option value="America/Tijuana">America/Tijuana</option>
              </select>
              <div class="field-help">Utilizada como referencia local para recordatorios y vistas de tiempo.</div>
            </div>
          </div>

          <div class="field" style="margin-top:8px;">
            <div class="field-label">Notificaciones (local)</div>

            <div class="switch-row" style="margin-top:4px;">
              <div>
                <div class="switch-label">Recordatorios por correo</div>
                <div class="switch-description">
                  Activar sugerencias para pagos, vencimientos u otros avisos clave.
                </div>
              </div>
              <div class="toggle on" data-local-toggle="mail-reminders">
                <div class="toggle-dot"></div>
              </div>
            </div>

            <div class="switch-row" style="margin-top:6px;">
              <div>
                <div class="switch-label">Notificaciones en navegador</div>
                <div class="switch-description">
                  Intentaremos usar notificaciones del navegador cuando estén permitidas.
                </div>
              </div>
              <div class="toggle" data-local-toggle="browser-notifications">
                <div class="toggle-dot"></div>
              </div>
            </div>
          </div>

          <button type="button" class="btn-secondary" style="margin-top:10px;">
            <span aria-hidden="true">💾</span>
            <span>Guardar preferencias de interfaz</span>
          </button>
        </div>
      </section>

      {{-- DATOS Y PRIVACIDAD --}}
      <section class="card-settings">
        <h2>Datos y privacidad</h2>
        <div class="card-sub">Control sobre tus datos y sesiones.</div>

        <div class="fields-grid">
          <div class="field">
            <div class="field-label">Sesiones activas</div>
            <div class="field-text">
              Este dispositivo<br>
              <span style="font-weight:600;font-size:12px;color:#6b7280;">
                Navegador actual · {{ request()->ip() ?? 'IP desconocida' }}
              </span>
            </div>
            <div class="field-help">
              Si notas actividad sospechosa, cambia tu contraseña y cierra sesión en todos los dispositivos.
            </div>
            <form method="POST" action="{{ $rtLogout }}" style="margin-top:8px;">
              @csrf
              <button type="submit" class="btn-secondary">
                <span aria-hidden="true">🔒</span>
                <span>Cerrar sesión en este dispositivo</span>
              </button>
            </form>
          </div>

          <div class="field">
            <div class="field-label">Exportar información</div>
            <div class="field-text">
              Puedes exportar tus CFDI desde el módulo de facturación en formato CSV, y tu estado de cuenta desde la sección de Pagos/Facturación.
            </div>

            <div style="margin-top:8px;display:flex;flex-wrap:wrap;gap:8px;">
              <a href="{{ $isProPlan ? $rtExportMes : '#' }}"
                 class="btn-secondary {{ $isProPlan ? '' : 'locked' }}"
                 @if(!$isProPlan) aria-disabled="true" title="Exportación disponible en plan PRO" @endif>
                <span aria-hidden="true">⬇️</span>
                <span>Exportar CFDI (mes actual)</span>
              </a>

              <a href="{{ $rtBilling }}" class="btn-secondary">
                <span aria-hidden="true">📄</span>
                <span>Ver estado de cuenta</span>
              </a>
            </div>

            @if(!$isProPlan)
              <div class="lock-note" style="margin-top:6px;">
                La exportación masiva de CFDI se habilita al contratar el plan PRO.
              </div>
            @else
              <div class="badge-mini pro" style="margin-top:6px;">
                <span aria-hidden="true">✅</span> Exportación masiva activa en tu cuenta PRO.
              </div>
            @endif
          </div>
        </div>
      </section>

      {{-- ZONA DE RIESGO --}}
      <section class="card-settings danger-zone">
        <h2>Zona de riesgo</h2>
        <div class="danger-text">
          Opciones delicadas relacionadas con la baja o migración de la cuenta.
        </div>

        <div class="field" style="margin-top:8px;">
          <div class="field-label">Cancelar suscripción o migrar</div>
          <div class="danger-text" style="margin-top:4px;">
            @if($isProPlan)
              Si necesitas pausar el servicio, ajustar tu plan o migrar la información a otra cuenta, contáctanos por soporte.
            @else
              Tu plan es FREE. Para cancelaciones de suscripciones pagadas o migraciones avanzadas, se habilitarán opciones adicionales al contratar PRO.
            @endif
          </div>
        </div>

        <a href="{{ $rtSoporte }}" class="btn-primary" style="margin-top:10px;">
          <span aria-hidden="true">🛟</span>
          <span>Contactar a soporte para baja / migración</span>
        </a>
      </section>

    </div>{{-- /col derecha --}}
  </div>
</div>
@endsection

@push('scripts')
<script>
  // Toggles locales "de mentiritas" (solo front, para que se sienta vivo).
  document.addEventListener('DOMContentLoaded', () => {
    document.querySelectorAll('.toggle').forEach(tg => {
      tg.addEventListener('click', () => {
        // Evitar interacción si está "bloqueado" por CSS
        if (tg.closest('.locked')) return;
        tg.classList.toggle('on');
      });
    });
  });
</script>
@endpush
