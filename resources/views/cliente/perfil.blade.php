{{-- resources/views/cliente/perfil.blade.php (v9 · Perfil unificado al diseño Vault · FIX: Modales Emisor/Import inline + sin include que rompe routes) --}}
@extends('layouts.cliente')
@section('title','Perfil · Pactopia360')
@section('pageClass','page-perfil')

@push('styles')
  <link rel="stylesheet" href="{{ asset('assets/client/css/perfil-vault.css') }}?v=1.0">
@endpush

@section('content')
@php
  use Illuminate\Support\Facades\Route;

  // Datos desde el controlador (compat)
  $user              = $user ?? auth('web')->user();
  $cuenta            = $cuenta ?? ($user?->cuenta);
    // RFC efectivo (viene del controller: cuenta SOT + fallback SAT credentials)
  $rfcPerfil       = $rfcPerfil ?? null;
  $rfcPerfilSource = $rfcPerfilSource ?? null; // 'cuenta' | 'sat_credentials' | null


  $plan              = strtoupper((string)($plan ?? ($cuenta->plan_actual ?? $cuenta->plan ?? 'FREE')));
  $isPro             = (bool)($isPro ?? ($plan === 'PRO'));

  $emisores          = $emisores          ?? collect();
  $kpis              = $kpis              ?? [];
  $facturasResumen   = $facturasResumen   ?? [];
  $facturasRecientes = $facturasRecientes ?? collect();
  $estadoCuenta      = $estadoCuenta      ?? ['saldo'=>0,'movimientos_recientes'=>collect()];
  $compras           = $compras           ?? collect();

  $ini = strtoupper(mb_substr(trim((string)($user?->nombre ?? $user?->name ?? 'U')),0,1));

  $safeRoute = function(string $name, array $params = [], string $fallback = '#'){
      return Route::has($name) ? route($name, $params) : $fallback;
  };

  // Status coherente (si existe)
  $statusRaw = (string)($cuenta->estado_cuenta ?? $cuenta->status ?? '');
  $status = strtoupper($statusRaw ?: '—');
  $statusPill = 'pf-pill--status-off';
  if (str_contains($status, 'ACTIV')) $statusPill = 'pf-pill--status-ok';
  if (str_contains($status, 'SUSP') || str_contains($status, 'BLOQ')) $statusPill = 'pf-pill--status-bad';

  // KPIs (mantiene tu estructura actual)
  $kPlan   = $kpis['plan'] ?? $plan;
  $kTimb   = (int)($kpis['timbres_disponibles'] ?? ($cuenta->timbres_disponibles ?? 0));
  $kEmis   = (int)($kpis['emisores'] ?? ($emisores?->count() ?? 0));
  $kMes    = (int)($kpis['facturas_mes'] ?? 0);
  $kSaldo  = (float)($kpis['saldo'] ?? ($estadoCuenta['saldo'] ?? 0));

  $canBillingStatement = Route::has('cliente.billing.statement');
  $canBillingPlans     = Route::has('cliente.billing.plans');

  $canNewCfdi          = Route::has('cliente.facturacion.nuevo');
  $canEmisorEdit       = Route::has('cliente.emisores.edit');
  $canEmisorDestroy    = Route::has('cliente.emisores.destroy');
  $canAvatarUpload     = Route::has('cliente.perfil.avatar');

  // Rutas para modales (tolerantes: si no existen, no rompen)
  $canEmisorStore  = Route::has('cliente.emisores.store') || Route::has('cliente.perfil.emisor.store') || Route::has('cliente.perfil.store_emisor');
  $rtEmisorStore   = Route::has('cliente.emisores.store')
        ? route('cliente.emisores.store')
        : (Route::has('cliente.perfil.emisor.store')
            ? route('cliente.perfil.emisor.store')
            : (Route::has('cliente.perfil.store_emisor') ? route('cliente.perfil.store_emisor') : '#'));

  $canEmisorImport = Route::has('cliente.emisores.import') || Route::has('cliente.perfil.emisor.import') || Route::has('cliente.perfil.import_emisores');
  $rtEmisorImport  = Route::has('cliente.emisores.import')
        ? route('cliente.emisores.import')
        : (Route::has('cliente.perfil.emisor.import')
            ? route('cliente.perfil.emisor.import')
            : (Route::has('cliente.perfil.import_emisores') ? route('cliente.perfil.import_emisores') : '#'));
@endphp

<div class="p360-ui">
  <div class="pf-page">

    {{-- HEADER (Vault-like) --}}
    <section class="sat-card pf-header">
      <div class="pf-header-left">
        <div class="pf-title-icon" aria-hidden="true">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none">
            <path d="M12 12a4 4 0 1 0-4-4 4 4 0 0 0 4 4Zm0 2c-4.42 0-8 2.01-8 4.5V20h16v-1.5c0-2.49-3.58-4.5-8-4.5Z" fill="currentColor"/>
          </svg>
        </div>
        <div style="min-width:0;">
          <div class="pf-title-main">Perfil de la cuenta</div>
          <div class="pf-title-sub">
            @if($isPro)
              Estás usando <strong>Pactopia360 PRO</strong>. Acceso completo a emisores, KPIs y compras.
            @else
              Estás en <strong>FREE</strong>. Algunas funciones aparecen bloqueadas como “Solo PRO”.
            @endif
          </div>
        </div>
      </div>

      <div class="pf-header-right">
        <div class="pf-pills">
          <span class="pf-pill {{ $isPro ? 'pf-pill--pro' : 'pf-pill--free' }}">
            <span class="dot"></span> PLAN: {{ $plan }}
          </span>
          <span class="pf-pill {{ $statusPill }}">
            <span class="dot"></span> {{ $status }}
          </span>
        </div>

        <div class="pf-actions">
          <a class="pf-pillbtn" href="{{ $safeRoute('cliente.configuracion') }}">
            ⚙ Configuración
          </a>
          <a class="pf-pillbtn pf-pillbtn--brand" href="{{ $safeRoute('cliente.mi_cuenta') }}">
            ☰ Mi cuenta
          </a>

          @if(!$isPro)
            <a class="pf-pillbtn pf-pillbtn--brand"
               href="{{ $canBillingPlans ? route('cliente.billing.plans') : ($canBillingStatement ? route('cliente.billing.statement') : '#') }}"
               aria-disabled="{{ ($canBillingPlans || $canBillingStatement) ? 'false' : 'true' }}">
              ✦ Activar PRO
            </a>
          @endif
        </div>
      </div>
    </section>

    {{-- KPIs --}}
    @if($isPro)
      <section class="sat-card">
        <div class="pf-kpi-grid">
          <div class="pf-kpi">
            <div class="pf-kpi-label">Plan</div>
            <div class="pf-kpi-value" style="font-family:inherit;">{{ $kPlan }}</div>
            <div class="pf-kpi-sub">Licencia actual</div>
          </div>
          <div class="pf-kpi">
            <div class="pf-kpi-label">Timbres disponibles</div>
            <div class="pf-kpi-value">{{ number_format($kTimb) }}</div>
            <div class="pf-kpi-sub">En tu cuenta</div>
          </div>
          <div class="pf-kpi">
            <div class="pf-kpi-label">Emisores</div>
            <div class="pf-kpi-value">{{ number_format($kEmis) }}</div>
            <div class="pf-kpi-sub">Registrados</div>
          </div>
          <div class="pf-kpi">
            <div class="pf-kpi-label">CFDI este mes</div>
            <div class="pf-kpi-value">{{ number_format($kMes) }}</div>
            <div class="pf-kpi-sub">Emitidos</div>
          </div>
          <div class="pf-kpi">
            <div class="pf-kpi-label">Saldo</div>
            <div class="pf-kpi-value">${{ number_format($kSaldo,2) }}</div>
            <div class="pf-kpi-sub">Cuenta Pactopia360</div>
          </div>
        </div>
      </section>
    @endif

    {{-- Usuario + Organización --}}
    <section class="pf-grid-2">
      <div class="sat-card">
        <div class="pf-section-top" style="align-items:center;">
          <div style="display:flex; align-items:center; gap:.75rem;">
            <div class="pf-avatar" id="avatarBox" title="Actualizar foto">
              @if($user?->avatar_url)
                <img src="{{ $user->avatar_url }}" alt="Avatar">
              @else
                {{ $ini }}
              @endif
              <div class="pf-avatar-overlay">Subir</div>
            </div>
            <div style="min-width:0;">
              <div class="pf-block-title" style="margin:0;">
                {{ $user?->nombre ?? $user?->name ?? 'Usuario' }}
              </div>
              <div class="pf-block-sub" style="margin:0;">
                {{ $user?->email ?? 'Sin correo' }}
              </div>
            </div>
          </div>
        </div>

        <div class="pf-block-sub" style="margin-top:.75rem;">
          Tu perfil contiene la información principal de acceso a la plataforma.
        </div>
      </div>

      <div class="sat-card">
        <div class="pf-block-title">Organización</div>
        <div class="pf-block-sub">Datos generales de la cuenta y contratación.</div>

        <div class="pf-kv">
          <div class="k">Razón social</div>
          <div class="v" title="{{ $cuenta?->razon_social ?? $cuenta?->nombre_fiscal ?? '—' }}">
            {{ $cuenta?->razon_social ?? $cuenta?->nombre_fiscal ?? '—' }}
          </div>

          <div class="k">RFC</div>
          <div class="v" style="display:flex; align-items:center; gap:.5rem; flex-wrap:wrap;">
            <span class="pf-mono">{{ $rfcPerfil ? $rfcPerfil : '—' }}</span>

            @if($rfcPerfil)
              @php
                $src = strtoupper((string)($rfcPerfilSource ?? ''));
                $srcLabel = $src === 'CUENTA' ? 'CUENTA' : ($src === 'SAT_CREDENTIALS' ? 'SAT' : 'RFC');
                $srcCls = $src === 'CUENTA' ? 'pf-pill--status-ok' : ($src === 'SAT_CREDENTIALS' ? 'pf-pill--pro' : 'pf-pill--status-off');
              @endphp
              <span class="pf-pill {{ $srcCls }}" style="padding:.28rem .55rem; font-size:.72rem;">
                <span class="dot"></span> {{ $srcLabel }}
              </span>
            @endif
          </div>

          <div class="k">Plan</div>
          <div class="v">{{ $plan }}</div>

          <div class="k">Timbres</div>
          <div class="v">{{ number_format((int)($cuenta->timbres_disponibles ?? 0)) }}</div>

        </div>
      </div>
    </section>

    {{-- Emisores --}}
    <section class="sat-card">
      <div class="pf-section-top">
        <div>
          <div class="pf-block-title">Emisores de la cuenta</div>
          <div class="pf-block-sub">
            @if($isPro)
              Administra todos tus emisores. Puedes usarlos al crear CFDI y en automatizaciones.
            @else
              En FREE puedes crear emisores manualmente. La importación masiva está disponible en PRO.
            @endif
          </div>
        </div>

        <div class="pf-actions">
          <button class="pf-pillbtn" type="button" onclick="openEmisorModal()">＋ Nuevo emisor</button>

          @if($isPro)
            <button class="pf-pillbtn" type="button" onclick="openImportModal()">⇪ Importar masivo</button>
          @else
            <button class="pf-pillbtn" type="button" aria-disabled="true" title="Solo disponible en PRO">⇪ Importar masivo · PRO</button>
          @endif

          <a class="pf-pillbtn pf-pillbtn--brand"
             href="{{ $canNewCfdi ? route('cliente.facturacion.nuevo') : '#' }}"
             aria-disabled="{{ $canNewCfdi ? 'false' : 'true' }}">
            ✚ Crear CFDI
          </a>
        </div>
      </div>

      @if($emisores->isEmpty())
        <div class="pf-empty">Aún no tienes emisores. Crea el primero con “Nuevo emisor”.</div>
      @else
        <div class="pf-table-wrap">
          <table class="pf-table">
            <thead>
              <tr>
                <th>RFC</th>
                <th>Razón social</th>
                <th>Nombre comercial</th>
                <th>Email</th>
                <th>Régimen</th>
                <th>Grupo</th>
                <th style="text-align:center;">Acciones</th>
              </tr>
            </thead>
            <tbody>
              @foreach($emisores as $e)
                <tr>
                  <td class="pf-mono">{{ $e->rfc }}</td>
                  <td title="{{ $e->razon_social }}">{{ $e->razon_social }}</td>
                  <td>{{ $e->nombre_comercial ?? '—' }}</td>
                  <td>{{ $e->email ?? '—' }}</td>
                  <td>{{ $e->regimen_fiscal ?? '—' }}</td>
                  <td>{{ $e->grupo ?? '—' }}</td>
                  <td style="text-align:center;">
                    @if($canEmisorEdit)
                      <a class="pf-iconbtn" href="{{ route('cliente.emisores.edit',$e->id) }}">EDITAR</a>
                    @else
                      <a class="pf-iconbtn" href="#" aria-disabled="true">EDITAR</a>
                    @endif

                    @if($canNewCfdi)
                      <a class="pf-iconbtn" href="{{ route('cliente.facturacion.nuevo',['emisor_id'=>$e->id]) }}">USAR</a>
                    @else
                      <a class="pf-iconbtn" href="#" aria-disabled="true">USAR</a>
                    @endif

                    <form method="POST"
                          @if($canEmisorDestroy) action="{{ route('cliente.emisores.destroy',$e->id) }}" @endif
                          onsubmit="return confirm('¿Eliminar emisor {{ $e->rfc }}?')"
                          style="display:inline">
                      @csrf @method('DELETE')
                      <button type="submit"
                              class="pf-iconbtn pf-iconbtn--danger"
                              @if(!$canEmisorDestroy) aria-disabled="true" type="button" onclick="return false;" @endif>
                        ELIMINAR
                      </button>
                    </form>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @endif
    </section>

    {{-- Compras y Pagos --}}
    <section class="pf-grid-2">
      <div class="sat-card">
        <div class="pf-block-title">Compras</div>

        @if($isPro)
          <div class="pf-block-sub">Historial de compras de timbres, planes y addons de tu cuenta.</div>

          @if($compras->isEmpty())
            <div class="pf-empty">Aún no registras compras en tu cuenta.</div>
          @else
            <div class="pf-table-wrap" style="margin-top:.25rem;">
              <table class="pf-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Descripción</th>
                    <th>Folio</th>
                    <th>Moneda</th>
                    <th style="text-align:right;">Total</th>
                    <th>Estatus</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($compras as $o)
                    <tr>
                      <td>
                        @if($o->created_at instanceof \Carbon\Carbon)
                          {{ $o->created_at->format('d/m/Y H:i') }}
                        @else
                          {{ $o->created_at ?? '—' }}
                        @endif
                      </td>
                      <td>{{ $o->descripcion ?? '—' }}</td>
                      <td>{{ $o->folio ?? '—' }}</td>
                      <td>{{ $o->moneda ?? 'MXN' }}</td>
                      <td class="pf-mono" style="text-align:right;">${{ number_format((float)($o->total ?? 0),2) }}</td>
                      <td>{{ strtoupper($o->status ?? $o->estatus ?? '—') }}</td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif

          <div class="pf-actions" style="margin-top:.75rem; justify-content:flex-start;">
            <a class="pf-pillbtn"
               href="{{ $canBillingStatement ? route('cliente.billing.statement') : '#' }}"
               aria-disabled="{{ $canBillingStatement ? 'false' : 'true' }}">
              🧾 Ver estado de cuenta
            </a>
          </div>
        @else
          <div class="pf-block-sub">
            En FREE no se muestra el historial detallado de compras. Al activar PRO podrás ver órdenes, facturas y estado de cuenta.
          </div>

          <div class="pf-actions" style="justify-content:flex-start;">
            <a class="pf-pillbtn pf-pillbtn--brand"
               href="{{ $canBillingPlans ? route('cliente.billing.plans') : '#' }}"
               aria-disabled="{{ $canBillingPlans ? 'false' : 'true' }}">
              ✦ Ver planes PRO
            </a>
          </div>
        @endif
      </div>

      <div class="sat-card">
        <div class="pf-block-title">Pagos y saldo</div>

        @if($isPro)
          <div class="pf-kv">
            <div class="k">Saldo actual</div>
            <div class="v pf-mono">${{ number_format((float)($estadoCuenta['saldo'] ?? 0),2) }}</div>
          </div>

          <div class="pf-block-title" style="margin-top:1rem;">Movimientos recientes</div>
          @php $movs = $estadoCuenta['movimientos_recientes'] ?? collect(); @endphp

          @if(!$movs || $movs->isEmpty())
            <div class="pf-empty">No hay movimientos recientes en tu cuenta.</div>
          @else
            <div class="pf-table-wrap">
              <table class="pf-table">
                <thead>
                  <tr>
                    <th>Fecha</th>
                    <th>Concepto</th>
                    <th>Tipo</th>
                    <th style="text-align:right;">Monto</th>
                    @if(isset($movs[0]) && is_object($movs[0]) && property_exists($movs[0],'saldo'))
                      <th style="text-align:right;">Saldo</th>
                    @endif
                  </tr>
                </thead>
                <tbody>
                  @foreach($movs as $m)
                    <tr>
                      <td>{{ $m->fecha ?? '—' }}</td>
                      <td>{{ $m->concepto ?? '—' }}</td>
                      <td>{{ strtoupper($m->tipo ?? '—') }}</td>
                      <td class="pf-mono" style="text-align:right;">${{ number_format((float)($m->monto ?? 0),2) }}</td>
                      @if(is_object($m) && property_exists($m,'saldo'))
                        <td class="pf-mono" style="text-align:right;">${{ number_format((float)($m->saldo ?? 0),2) }}</td>
                      @endif
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          @endif

          <div class="pf-block-sub" style="margin-top:.75rem;">
            Próximamente podrás gestionar métodos de pago y facturas desde aquí.
          </div>
        @else
          <div class="pf-block-sub">
            El detalle de saldo y movimientos forma parte de las herramientas PRO.
          </div>
        @endif
      </div>
    </section>

  </div>
</div>

{{-- ===========================================================
   MODAL: Subir foto de perfil (Vault-like)
=========================================================== --}}
<dialog class="pf-modal" id="avatarModal">
  <header>
    <strong>Actualizar foto de perfil</strong>
    <button class="pf-pillbtn" type="button" onclick="closeAvatarModal()">✕</button>
  </header>
  <div class="body">
    <form id="avatarForm" method="POST" enctype="multipart/form-data"
          @if($canAvatarUpload) action="{{ route('cliente.perfil.avatar') }}" @endif>
      @csrf

      <div class="pf-field">
        <span class="pf-lbl">Seleccionar imagen</span>
        <input class="pf-input" type="file" name="avatar" id="avatarInput" accept="image/*" required>
        <div class="pf-hint">Formatos: JPG, PNG, WEBP. Recomendado 400×400 px.</div>
      </div>

      <div class="pf-actions" style="justify-content:flex-end;">
        <button type="button" class="pf-pillbtn" onclick="closeAvatarModal()">Cancelar</button>
        <button type="submit" class="pf-pillbtn pf-pillbtn--brand"
                aria-disabled="{{ $canAvatarUpload ? 'false' : 'true' }}"
                @if(!$canAvatarUpload) type="button" onclick="return false;" title="Falta definir route: cliente.perfil.avatar" @endif>
          Subir foto
        </button>
      </div>
    </form>
  </div>
</dialog>

{{-- ===========================================================
   MODAL: Nuevo Emisor  (ID EXACTO para tu JS: modal-emisor)
=========================================================== --}}
<dialog class="pf-modal" id="modal-emisor">
  <header>
    <strong>Nuevo emisor</strong>
    <button class="pf-pillbtn" type="button" data-modal-close>✕</button>
  </header>

  <div class="body">
    <form method="POST"
          enctype="multipart/form-data"
          action="{{ $rtEmisorStore }}"
          @if(!$canEmisorStore) onsubmit="return false;" @endif>
      @csrf

      <div class="pf-grid-2" style="margin-top:.25rem;">
        <div class="pf-field">
          <span class="pf-lbl">RFC</span>
          <input class="pf-input" name="rfc" maxlength="13" placeholder="AAA010101AAA" required>
          <div class="pf-hint">Obligatorio. 12 (persona moral) / 13 (persona física).</div>
        </div>

        <div class="pf-field">
          <span class="pf-lbl">Email</span>
          <input class="pf-input" type="email" name="email" maxlength="190" placeholder="facturacion@empresa.com" required>
          <div class="pf-hint">Obligatorio para notificaciones y timbrado.</div>
        </div>

        <div class="pf-field" style="grid-column:1/-1;">
          <span class="pf-lbl">Razón social</span>
          <input class="pf-input" name="razon_social" maxlength="190" placeholder="Mi Empresa S.A. de C.V." required>
        </div>

        <div class="pf-field">
          <span class="pf-lbl">Nombre comercial</span>
          <input class="pf-input" name="nombre_comercial" maxlength="190" placeholder="Mi Empresa">
        </div>

        <div class="pf-field">
          <span class="pf-lbl">Régimen fiscal</span>
          <input class="pf-input" name="regimen_fiscal" maxlength="10" placeholder="601" required>
          <div class="pf-hint">Ej. 601, 603, 605…</div>
        </div>

        <div class="pf-field">
          <span class="pf-lbl">Grupo</span>
          <input class="pf-input" name="grupo" maxlength="60" placeholder="General">
        </div>

        <div class="pf-field">
          <span class="pf-lbl">Código postal</span>
          <input class="pf-input" name="direccion[cp]" maxlength="10" placeholder="01000" required>
        </div>

        <div class="pf-field" style="grid-column:1/-1;">
          <span class="pf-lbl">Dirección</span>
          <input class="pf-input" name="direccion[direccion]" maxlength="250" placeholder="Calle, número, colonia (opcional)">
        </div>

        <div class="pf-field">
          <span class="pf-lbl">Ciudad / Municipio</span>
          <input class="pf-input" name="direccion[ciudad]" maxlength="120" placeholder="CDMX (opcional)">
        </div>

        <div class="pf-field">
          <span class="pf-lbl">Estado</span>
          <input class="pf-input" name="direccion[estado]" maxlength="120" placeholder="Ciudad de México (opcional)">
        </div>

        <div class="pf-field" style="grid-column:1/-1;">
          <span class="pf-lbl">Series JSON (opcional)</span>
          <textarea class="pf-input" name="series_json" rows="3" placeholder='[{"serie":"A","folio":1},{"serie":"B","folio":100}]'></textarea>
          <div class="pf-hint">Si no lo usas, déjalo vacío.</div>
        </div>
      </div>

      <div class="pf-actions" style="justify-content:flex-end; margin-top:.75rem;">
        <button type="button" class="pf-pillbtn" data-modal-close>Cancelar</button>

        <button type="submit" class="pf-pillbtn pf-pillbtn--brand"
                aria-disabled="{{ $canEmisorStore ? 'false' : 'true' }}"
                @if(!$canEmisorStore) type="button" onclick="return false;" title="No existe route para guardar emisor (define una y listo)." @endif>
          Guardar emisor
        </button>
      </div>
    </form>

    @if(!$canEmisorStore)
      <div class="pf-empty" style="margin-top:.75rem;">
        Falta definir la ruta para guardar emisores. Define una route POST y apunta a <code>PerfilController@storeEmisor</code> (o la que uses).
      </div>
    @endif
  </div>
</dialog>

{{-- ===========================================================
   MODAL: Importación masiva (ID EXACTO para tu JS: modal-import)
=========================================================== --}}
<dialog class="pf-modal" id="modal-import">
  <header>
    <strong>Importación masiva de emisores</strong>
    <button class="pf-pillbtn" type="button" data-modal-close>✕</button>
  </header>

  <div class="body">
    <div class="pf-block-sub" style="margin-top:.25rem;">
      Sube un archivo <strong>CSV</strong> o <strong>JSON</strong>. Campos mínimos: <code>rfc</code>, <code>razon_social</code>.
    </div>

    <form method="POST"
          enctype="multipart/form-data"
          action="{{ $rtEmisorImport }}"
          @if(!$canEmisorImport) onsubmit="return false;" @endif>
      @csrf

      <div class="pf-field" style="margin-top:.75rem;">
        <span class="pf-lbl">Archivo</span>
        <input class="pf-input" type="file" name="file" accept=".csv,.txt,.json" required>
        <div class="pf-hint">Máx 10MB. CSV con headers o JSON array.</div>
      </div>

      <div class="pf-actions" style="justify-content:flex-end; margin-top:.75rem;">
        <button type="button" class="pf-pillbtn" data-modal-close>Cancelar</button>

        <button type="submit" class="pf-pillbtn pf-pillbtn--brand"
                aria-disabled="{{ $canEmisorImport ? 'false' : 'true' }}"
                @if(!$canEmisorImport) type="button" onclick="return false;" title="No existe route para importar emisores (define una y listo)." @endif>
          Importar
        </button>
      </div>
    </form>

    @if(!$canEmisorImport)
      <div class="pf-empty" style="margin-top:.75rem;">
        Falta definir la ruta para importar. Define una route POST y apunta a <code>PerfilController@importEmisores</code> (o la que uses).
      </div>
    @endif
  </div>
</dialog>

<script>
  // ===== Avatar interaction =====
  const avatarBox   = document.getElementById('avatarBox');
  const avatarModal = document.getElementById('avatarModal');

  function openAvatarModal(){ avatarModal && avatarModal.showModal(); }
  function closeAvatarModal(){ avatarModal && avatarModal.close(); }

  if (avatarBox){
    avatarBox.addEventListener('click', openAvatarModal);
  }

  // ===== Preview instantáneo antes de subir =====
  const avatarInput = document.getElementById('avatarInput');
  avatarInput && avatarInput.addEventListener('change', (e)=>{
    const file = e.target.files && e.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = ()=>{
      const img = document.createElement('img');
      img.src = reader.result;
      img.style.cssText = "width:100%;height:100%;object-fit:cover;border-radius:999px;";
      if (avatarBox){
        avatarBox.innerHTML = '';
        avatarBox.appendChild(img);
        const ov = document.createElement('div');
        ov.className = 'pf-avatar-overlay';
        ov.textContent = 'Subir';
        avatarBox.appendChild(ov);
      }
    };
    reader.readAsDataURL(file);
  });
</script>

<script>
/**
 * Pactopia360 · Perfil modales (dialog-first)
 * Define:
 * - openEmisorModal()
 * - openImportModal()
 *
 * IDs usados:
 * - #modal-emisor
 * - #modal-import
 */
(function () {
  function byId(id){ return document.getElementById(id); }

  function openDialog(el){
    if (!el) return;
    // dialog nativo
    if (typeof el.showModal === 'function') {
      if (!el.open) el.showModal();
      return;
    }
    // fallback (si no fuera dialog)
    el.classList.add('is-open');
    el.removeAttribute('hidden');
    el.setAttribute('aria-hidden','false');
    document.body.style.overflow = 'hidden';
  }

  function closeDialog(el){
    if (!el) return;
    if (typeof el.close === 'function') {
      if (el.open) el.close();
      return;
    }
    el.classList.remove('is-open');
    el.setAttribute('hidden','hidden');
    el.setAttribute('aria-hidden','true');
    document.body.style.overflow = '';
  }

  // Cierres por data-modal-close
  document.addEventListener('click', function(ev){
    const btn = ev.target && ev.target.closest ? ev.target.closest('[data-modal-close]') : null;
    if (!btn) return;
    const dlg = btn.closest('dialog, .pf-modal, [role="dialog"]');
    if (!dlg) return;
    ev.preventDefault();
    closeDialog(dlg);
  }, true);

  // Exponer funciones globales usadas por tus botones
  window.openEmisorModal = function(){
    const el = byId('modal-emisor');
    if (!el) {
      console.error('No existe modal de Emisor. Falta #modal-emisor en el DOM.');
      return;
    }
    openDialog(el);
  };

  window.openImportModal = function(){
    const el = byId('modal-import');
    if (!el) {
      console.error('No existe modal de Importación. Falta #modal-import en el DOM.');
      return;
    }
    openDialog(el);
  };
})();
</script>

@endsection
