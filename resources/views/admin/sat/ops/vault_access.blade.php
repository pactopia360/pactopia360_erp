{{-- C:\wamp64\www\pactopia360_erp\resources\views\admin\sat\ops\vault_access.blade.php --}}

@extends('layouts.admin')

@section('title', 'SAT · Acceso a bóvedas')
@section('pageClass', 'page-admin-sat-vault-access')

@section('page-header')
  <div class="vaHead">
    <div>
      <div class="vaKicker">ADMIN · SAT OPS</div>
      <h1 class="vaTitle">Acceso a bóvedas</h1>
      <div class="vaSub">
        Vista simplificada por líneas. Solo se muestra lo esencial y toda la administración se hace desde emergentes.
      </div>
    </div>

    <div class="vaHeadActions">
      <a class="vaBtn vaBtn--ghost" href="{{ route('admin.sat.ops.index') }}">Volver a SAT Ops</a>
    </div>
  </div>
@endsection

@section('content')
  <div class="vaWrap">

    @if(session('success'))
      <div class="vaAlert vaAlert--ok">{{ session('success') }}</div>
    @endif

    @if(session('error'))
      <div class="vaAlert vaAlert--error">{{ session('error') }}</div>
    @endif

    <div class="vaToolbar">
      <div class="vaSearchCard">
        <form method="GET" action="{{ route('admin.billing.vault_access.index') }}" class="vaSearchForm">
          <div class="vaField">
            <label for="q">Buscar cuenta</label>
            <input
              id="q"
              name="q"
              type="text"
              value="{{ $q }}"
              placeholder="RFC, razón social, email, código cliente, UUID de cuenta">
          </div>

          <div class="vaSearchActions">
            <button type="submit" class="vaBtn vaBtn--primary">Buscar</button>
            <a href="{{ route('admin.billing.vault_access.index') }}" class="vaBtn vaBtn--ghost">Limpiar</a>
          </div>
        </form>
      </div>
    </div>

    @if($accounts->count() === 0)
      <div class="vaEmpty">
        <div class="vaEmptyTitle">No se encontraron cuentas</div>
        <div class="vaEmptyText">Prueba con RFC, razón social, email o código de cliente.</div>
      </div>
    @endif

    <div class="vaList">
      <div class="vaListHead">
        <div>Cuenta</div>
        <div>Resumen</div>
        <div>Estado</div>
        <div>Acciones</div>
      </div>

      @foreach($accounts as $account)
        @php
          $module = $moduleMap[$account->id] ?? ['enabled' => false, 'state' => 'inactive', 'admin_account_id' => 0];
          $usersAccess = $accessMap[$account->id] ?? [];
          $vaultV1Enabled = (int)($account->vault_active ?? 0) === 1;
          $quotaBytes = (int)($account->vault_quota_bytes ?? 0);
          $planLabel = strtoupper((string)($account->plan_actual ?? $account->plan ?? 'FREE'));
          $displayName = $account->razon_social ?: ($account->nombre_comercial ?: 'Cuenta sin nombre');
          $usersCount = $account->usuarios->count();

          $enabledUsersV1Count = collect($account->usuarios)->filter(function ($user) use ($usersAccess) {
              $ua = $usersAccess[$user->id] ?? null;
              return (bool) data_get($ua?->meta, 'vault_access.v1', false);
          })->count();

          $enabledUsersV2Count = collect($account->usuarios)->filter(function ($user) use ($usersAccess) {
              $ua = $usersAccess[$user->id] ?? null;
              return (bool) data_get($ua?->meta, 'vault_access.v2', (bool) ($ua?->can_access_vault ?? false));
          })->count();

          $modalV1Id = 'va-modal-v1-' . $account->id;
          $modalV2Id = 'va-modal-v2-' . $account->id;
          $modalUsersId = 'va-modal-users-' . $account->id;
          $modalAddUserId = 'va-modal-add-user-' . $account->id;
        @endphp

        <article class="vaRow">
          <div class="vaCell vaCell--account">
            <div class="vaAccountMain">
              <div class="vaAccountTitleRow">
                <h2 class="vaAccountTitle">{{ $displayName }}</h2>
                <span class="vaPlan">{{ $planLabel }}</span>
              </div>

              <div class="vaAccountMeta">
                <span><strong>RFC:</strong> {{ $account->rfc_padre ?: '—' }}</span>
                <span><strong>Email:</strong> {{ $account->email ?: '—' }}</span>
              </div>
            </div>
          </div>

          <div class="vaCell vaCell--summary">
            <div class="vaSummaryLine">
              <span><strong>Cuenta:</strong> {{ $account->id }}</span>
              <span><strong>Admin:</strong> {{ (int)($account->admin_account_id ?? 0) > 0 ? $account->admin_account_id : '—' }}</span>
              <span><strong>Cuota:</strong> {{ number_format($quotaBytes) }}</span>
              <span><strong>Usuarios:</strong> {{ $usersCount }}</span>
              <span><strong>Acceso V1:</strong> {{ $enabledUsersV1Count }}/{{ $usersCount }}</span>
              <span><strong>Acceso V2:</strong> {{ $enabledUsersV2Count }}/{{ $usersCount }}</span>
            </div>
          </div>

          <div class="vaCell vaCell--status">
            <div class="vaStatusStack">
              <span class="vaBadge {{ $vaultV1Enabled ? 'is-on' : 'is-off' }}">
                V1 {{ $vaultV1Enabled ? 'activa' : 'inactiva' }}
              </span>

              <span class="vaBadge {{ $module['enabled'] ? 'is-on' : 'is-off' }}">
                V2 {{ $module['enabled'] ? 'activa' : 'inactiva' }}
              </span>
            </div>
          </div>

          <div class="vaCell vaCell--actions">
            <div class="vaActions">
              <button
                type="button"
                class="vaBtn vaBtn--primary"
                data-va-open="{{ $modalV1Id }}">
                V1
              </button>

              <button
                type="button"
                class="vaBtn vaBtn--primary"
                data-va-open="{{ $modalV2Id }}">
                V2
              </button>

              <button
                type="button"
                class="vaBtn vaBtn--ghost"
                data-va-open="{{ $modalUsersId }}">
                Usuarios
              </button>
            </div>
          </div>
        </article>

        {{-- Modal V1 --}}
        <div class="vaModal" id="{{ $modalV1Id }}" aria-hidden="true">
          <div class="vaModalBackdrop" data-va-close="{{ $modalV1Id }}"></div>
          <div class="vaModalDialog">
            <div class="vaModalHead">
              <div>
                <div class="vaModalKicker">BÓVEDA V1</div>
                <h3 class="vaModalTitle">{{ $displayName }}</h3>
                <div class="vaModalSub">Control general por cuenta para la bóveda tradicional.</div>
              </div>

              <button type="button" class="vaIconBtn" data-va-close="{{ $modalV1Id }}">×</button>
            </div>

            <div class="vaModalBody">
              <div class="vaModalFacts">
                <div class="vaFact">
                  <span class="k">Estado actual</span>
                  <span class="v">{{ $vaultV1Enabled ? 'Activa' : 'Inactiva' }}</span>
                </div>

                <div class="vaFact">
                  <span class="k">vault_active</span>
                  <span class="v">{{ $vaultV1Enabled ? '1' : '0' }}</span>
                </div>

                <div class="vaFact">
                  <span class="k">vault_quota_bytes</span>
                  <span class="v">{{ number_format($quotaBytes) }}</span>
                </div>
              </div>
            </div>

            <div class="vaModalFoot">
              <form method="POST" action="{{ route('admin.billing.vault_access.v1.update', ['cuentaId' => $account->id, 'q' => $q]) }}" class="vaInlineForm">
                @csrf
                <input type="hidden" name="enabled" value="{{ $vaultV1Enabled ? '0' : '1' }}">
                <button type="submit" class="vaBtn {{ $vaultV1Enabled ? 'vaBtn--danger' : 'vaBtn--primary' }}">
                  {{ $vaultV1Enabled ? 'Desactivar v1' : 'Activar v1' }}
                </button>
              </form>

              <button type="button" class="vaBtn vaBtn--ghost" data-va-close="{{ $modalV1Id }}">Cerrar</button>
            </div>
          </div>
        </div>

        {{-- Modal V2 módulo --}}
        <div class="vaModal" id="{{ $modalV2Id }}" aria-hidden="true">
          <div class="vaModalBackdrop" data-va-close="{{ $modalV2Id }}"></div>
          <div class="vaModalDialog">
            <div class="vaModalHead">
              <div>
                <div class="vaModalKicker">MÓDULO SAT BÓVEDA V2</div>
                <h3 class="vaModalTitle">{{ $displayName }}</h3>
                <div class="vaModalSub">Activa o desactiva el módulo a nivel cuenta administradora.</div>
              </div>

              <button type="button" class="vaIconBtn" data-va-close="{{ $modalV2Id }}">×</button>
            </div>

            <div class="vaModalBody">
              <div class="vaModalFacts">
                <div class="vaFact">
                  <span class="k">Estado actual</span>
                  <span class="v">{{ $module['enabled'] ? 'Activo' : 'Inactivo' }}</span>
                </div>

                <div class="vaFact">
                  <span class="k">modules_state.sat_boveda_v2</span>
                  <span class="v">{{ $module['state'] ?: 'inactive' }}</span>
                </div>

                <div class="vaFact">
                  <span class="k">admin_account_id</span>
                  <span class="v">{{ $module['admin_account_id'] > 0 ? $module['admin_account_id'] : '—' }}</span>
                </div>
              </div>
            </div>

            <div class="vaModalFoot">
              <form method="POST" action="{{ route('admin.billing.vault_access.v2_module.update', ['cuentaId' => $account->id, 'q' => $q]) }}" class="vaInlineForm">
                @csrf
                <input type="hidden" name="enabled" value="{{ $module['enabled'] ? '0' : '1' }}">
                <button type="submit" class="vaBtn {{ $module['enabled'] ? 'vaBtn--danger' : 'vaBtn--primary' }}">
                  {{ $module['enabled'] ? 'Desactivar módulo v2' : 'Activar módulo v2' }}
                </button>
              </form>

              <button type="button" class="vaBtn vaBtn--ghost" data-va-close="{{ $modalV2Id }}">Cerrar</button>
            </div>
          </div>
        </div>

        {{-- Modal agregar usuario --}}
        <div class="vaModal" id="{{ $modalAddUserId }}" aria-hidden="true">
          <div class="vaModalBackdrop" data-va-close="{{ $modalAddUserId }}"></div>
          <div class="vaModalDialog">
            <div class="vaModalHead">
              <div>
                <div class="vaModalKicker">AGREGAR USUARIO</div>
                <h3 class="vaModalTitle">{{ $displayName }}</h3>
                <div class="vaModalSub">Crea un usuario nuevo y, si quieres, asígnale acceso a bóvedas desde aquí mismo.</div>
              </div>

              <button type="button" class="vaIconBtn" data-va-close="{{ $modalAddUserId }}">×</button>
            </div>

            <form method="POST" action="{{ route('admin.billing.vault_access.users.store', ['cuentaId' => $account->id, 'q' => $q]) }}">
              @csrf

              <div class="vaModalBody">
                <div class="vaFormGrid">
                  <div class="vaField">
                    <label>Nombre</label>
                    <input type="text" name="nombre" required>
                  </div>

                  <div class="vaField">
                    <label>Email</label>
                    <input type="email" name="email" required>
                  </div>

                  <div class="vaField">
                    <label>Rol</label>
                    <input type="text" name="rol" value="usuario">
                  </div>

                  <div class="vaField">
                    <label>Tipo</label>
                    <input type="text" name="tipo" value="usuario">
                  </div>

                  <div class="vaField">
                    <label>Password</label>
                    <input type="text" name="password" placeholder="Opcional, si lo dejas vacío se genera una temporal">
                  </div>
                </div>

                <div class="vaChecksGrid">
                  <label class="vaCheck">
                    <input type="checkbox" name="activo" value="1" checked>
                    <span>Activo</span>
                  </label>

                  <label class="vaCheck">
                    <input type="checkbox" name="must_change_password" value="1" checked>
                    <span>Forzar cambio de password</span>
                  </label>

                  <label class="vaCheck">
                    <input type="checkbox" name="can_access_v1" value="1">
                    <span>Acceso bóveda v1</span>
                  </label>

                  <label class="vaCheck">
                    <input type="checkbox" name="can_access_v2" value="1">
                    <span>Acceso bóveda v2</span>
                  </label>

                  <label class="vaCheck">
                    <input type="checkbox" name="can_upload_metadata" value="1">
                    <span>Metadata</span>
                  </label>

                  <label class="vaCheck">
                    <input type="checkbox" name="can_upload_xml" value="1">
                    <span>XML</span>
                  </label>

                  <label class="vaCheck">
                    <input type="checkbox" name="can_export" value="1">
                    <span>Exportar</span>
                  </label>
                </div>
              </div>

              <div class="vaModalFoot">
                <button type="submit" class="vaBtn vaBtn--primary">Agregar usuario</button>
                <button type="button" class="vaBtn vaBtn--ghost" data-va-close="{{ $modalAddUserId }}">Cerrar</button>
              </div>
            </form>
          </div>
        </div>

        {{-- Modal usuarios --}}
        <div class="vaModal" id="{{ $modalUsersId }}" aria-hidden="true">
          <div class="vaModalBackdrop" data-va-close="{{ $modalUsersId }}"></div>
          <div class="vaModalDialog vaModalDialog--xl">
            <div class="vaModalHead">
              <div>
                <div class="vaModalKicker">PERMISOS POR USUARIO</div>
                <h3 class="vaModalTitle">{{ $displayName }}</h3>
                <div class="vaModalSub">Permisos de acceso por usuario para Bóveda v1, Bóveda v2 y acciones operativas.</div>
              </div>

              <div class="vaModalHeadActions">
                <button type="button" class="vaBtn vaBtn--primary" data-va-open="{{ $modalAddUserId }}" data-va-close-current="{{ $modalUsersId }}">
                  Agregar usuario
                </button>
                <button type="button" class="vaIconBtn" data-va-close="{{ $modalUsersId }}">×</button>
              </div>
            </div>

            <form method="POST" action="{{ route('admin.billing.vault_access.v2_users.update', ['cuentaId' => $account->id, 'q' => $q]) }}">
              @csrf

              <div class="vaModalBody">
                @if($account->usuarios->count() > 0)
                  <div class="vaUsersCards">
                    @foreach($account->usuarios as $user)
                      @php
                        $ua = $usersAccess[$user->id] ?? null;
                        $canAccessV1 = (bool) data_get($ua?->meta, 'vault_access.v1', false);
                        $canAccessV2 = (bool) data_get($ua?->meta, 'vault_access.v2', (bool) ($ua?->can_access_vault ?? false));

                        $hasAnyAccess = $ua && (
                          $canAccessV1 ||
                          $canAccessV2 ||
                          (bool) ($ua->can_upload_metadata ?? false) ||
                          (bool) ($ua->can_upload_xml ?? false) ||
                          (bool) ($ua->can_export ?? false)
                        );

                        $editModalId = 'va-modal-edit-user-' . $account->id . '-' . $user->id;
                      @endphp

                      <div class="vaUserCard">
                        <div class="vaUserCardMain">
                          <div class="vaUserCardTitleRow">
                            <div>
                              <div class="vaUserName">{{ $user->nombre ?: 'Usuario sin nombre' }}</div>
                              <div class="vaUserRole">
                                {{ $user->rol ?: '—' }}
                                @if(method_exists($user, 'isOwner') && $user->isOwner())
                                  · owner
                                @endif
                                · {{ $user->email ?: '—' }}
                              </div>
                            </div>

                            <div class="vaUserActions">
                              <button
                                type="button"
                                class="vaBtn vaBtn--ghost vaBtn--sm"
                                disabled
                                title="Edición detallada temporalmente deshabilitada para estabilizar el módulo">
                                Editar
                              </button>

                              @if($hasAnyAccess)
                                <form
                                  method="POST"
                                  action="{{ route('admin.billing.vault_access.v2_users.delete', ['cuentaId' => $account->id, 'usuarioId' => $user->id, 'q' => $q]) }}"
                                  class="vaDeleteInlineForm"
                                  onsubmit="return confirm('¿Seguro que deseas eliminar todos los accesos de este usuario a las bóvedas?');">
                                  @csrf
                                  <button type="submit" class="vaBtn vaBtn--danger vaBtn--sm">
                                    Eliminar acceso
                                  </button>
                                </form>
                              @else
                                <span class="vaMutedAction">Sin acceso</span>
                              @endif
                            </div>
                          </div>

                          <div class="vaPermissionRow">
                            <label class="vaCheck">
                              <input type="hidden" name="users[{{ $user->id }}][can_access_v1]" value="0">
                              <input type="checkbox" name="users[{{ $user->id }}][can_access_v1]" value="1" {{ $canAccessV1 ? 'checked' : '' }}>
                              <span>Acceso bóveda v1</span>
                            </label>

                            <label class="vaCheck">
                              <input type="hidden" name="users[{{ $user->id }}][can_access_v2]" value="0">
                              <input type="checkbox" name="users[{{ $user->id }}][can_access_v2]" value="1" {{ $canAccessV2 ? 'checked' : '' }}>
                              <span>Acceso bóveda v2</span>
                            </label>

                            <label class="vaCheck">
                              <input type="hidden" name="users[{{ $user->id }}][can_upload_metadata]" value="0">
                              <input type="checkbox" name="users[{{ $user->id }}][can_upload_metadata]" value="1" {{ ($ua && $ua->can_upload_metadata) ? 'checked' : '' }}>
                              <span>Metadata</span>
                            </label>

                            <label class="vaCheck">
                              <input type="hidden" name="users[{{ $user->id }}][can_upload_xml]" value="0">
                              <input type="checkbox" name="users[{{ $user->id }}][can_upload_xml]" value="1" {{ ($ua && $ua->can_upload_xml) ? 'checked' : '' }}>
                              <span>XML</span>
                            </label>

                            <label class="vaCheck">
                              <input type="hidden" name="users[{{ $user->id }}][can_export]" value="0">
                              <input type="checkbox" name="users[{{ $user->id }}][can_export]" value="1" {{ ($ua && $ua->can_export) ? 'checked' : '' }}>
                              <span>Exportar</span>
                            </label>
                          </div>
                        </div>
                      </div>
                    @endforeach
                  </div>
                @else
                  <div class="vaNoUsersCard">
                    Esta cuenta no tiene usuarios cargados.
                  </div>
                @endif
              </div>

              <div class="vaModalFoot">
                @if($account->usuarios->count() > 0)
                  <button type="submit" class="vaBtn vaBtn--primary">Guardar permisos de bóvedas</button>
                @endif

                <button type="button" class="vaBtn vaBtn--ghost" data-va-close="{{ $modalUsersId }}">Cerrar</button>
              </div>
            </form>
          </div>
        </div>
      @endforeach
    </div>

    @if($accounts->hasPages())
      <div class="vaPagination">
        {{ $accounts->links() }}
      </div>
    @endif
  </div>
@endsection

@push('styles')
<style>
  .page-admin-sat-vault-access .page-container{ padding-top:14px; }

  .vaWrap{
    display:grid;
    gap:16px;
  }

  .vaHead{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:16px;
    padding:14px 16px;
  }

  .vaKicker{
    font:900 11px/1 system-ui;
    letter-spacing:.12em;
    color:var(--muted);
  }

  .vaTitle{
    margin:6px 0 0;
    font:950 26px/1.05 system-ui;
    letter-spacing:-.03em;
    color:var(--text);
  }

  .vaSub{
    margin-top:8px;
    color:var(--muted);
    font:650 13px/1.5 system-ui;
    max-width:860px;
  }

  .vaSearchCard,
  .vaEmpty,
  .vaList{
    border:1px solid var(--bd);
    background:var(--card-bg);
    border-radius:18px;
    box-shadow:var(--shadow-1);
  }

  .vaSearchCard{
    padding:16px;
  }

  .vaSearchForm{
    display:grid;
    grid-template-columns:minmax(280px, 1fr) auto;
    gap:12px;
    align-items:end;
  }

  .vaField{
    display:grid;
    gap:8px;
  }

  .vaField label{
    font:800 12px/1 system-ui;
    color:var(--muted);
  }

  .vaField input{
    width:100%;
    min-height:46px;
    border:1px solid var(--bd);
    border-radius:14px;
    background:color-mix(in oklab, var(--panel-bg) 78%, transparent);
    color:var(--text);
    padding:12px 14px;
    outline:none;
  }

  .vaField input:focus{
    border-color:color-mix(in oklab, #8b5cf6 42%, var(--bd));
    box-shadow:0 0 0 4px color-mix(in oklab, #8b5cf6 14%, transparent);
  }

  .vaSearchActions,
  .vaHeadActions,
  .vaActions,
  .vaModalFoot,
  .vaInlineForm,
  .vaUserActions,
  .vaModalHeadActions{
    display:flex;
    gap:8px;
    flex-wrap:wrap;
  }

  .vaBtn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:40px;
    padding:10px 14px;
    border-radius:12px;
    border:1px solid var(--bd);
    text-decoration:none;
    cursor:pointer;
    font:850 13px/1 system-ui;
    color:var(--text);
    background:color-mix(in oklab, var(--card-bg) 88%, transparent);
    transition:transform .16s ease, filter .16s ease;
  }

  .vaBtn:hover{
    filter:brightness(.98);
    transform:translateY(-1px);
  }

  .vaBtn--primary{
    background:#8b5cf6;
    border-color:#8b5cf6;
    color:#fff;
  }

  .vaBtn--danger{
    background:#ef4444;
    border-color:#ef4444;
    color:#fff;
  }

  .vaBtn--ghost{
    background:color-mix(in oklab, var(--card-bg) 88%, transparent);
  }

  .vaBtn--sm{
    min-height:34px;
    padding:8px 10px;
    border-radius:10px;
    font:800 12px/1 system-ui;
  }

  .vaAlert{
    padding:12px 14px;
    border-radius:14px;
    border:1px solid var(--bd);
    font:700 13px/1.45 system-ui;
  }

  .vaAlert--ok{
    background:color-mix(in oklab, #10b981 13%, var(--card-bg));
    border-color:color-mix(in oklab, #10b981 35%, var(--bd));
  }

  .vaAlert--error{
    background:color-mix(in oklab, #ef4444 10%, var(--card-bg));
    border-color:color-mix(in oklab, #ef4444 35%, var(--bd));
  }

  .vaEmpty{
    padding:28px 18px;
  }

  .vaEmptyTitle{
    font:900 16px/1 system-ui;
    margin-bottom:8px;
  }

  .vaEmptyText{
    color:var(--muted);
    font:650 13px/1.5 system-ui;
  }

  .vaList{
    overflow:hidden;
  }

  .vaListHead,
  .vaRow{
    display:grid;
    grid-template-columns:minmax(260px, 1.2fr) minmax(300px, 1.3fr) minmax(170px, .8fr) minmax(220px, .9fr);
    gap:14px;
    align-items:center;
  }

  .vaListHead{
    padding:14px 16px;
    border-bottom:1px solid var(--bd);
    background:color-mix(in oklab, var(--panel-bg) 82%, transparent);
    font:850 12px/1 system-ui;
    color:var(--muted);
  }

  .vaRow{
    padding:14px 16px;
    border-bottom:1px solid var(--bd);
  }

  .vaRow:last-child{
    border-bottom:0;
  }

  .vaRow:hover{
    background:color-mix(in oklab, var(--panel-bg) 50%, transparent);
  }

  .vaCell{
    min-width:0;
  }

  .vaAccountTitleRow,
  .vaUserCardTitleRow{
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:10px;
    flex-wrap:wrap;
  }

  .vaAccountTitle{
    margin:0;
    font:900 18px/1.08 system-ui;
    letter-spacing:-.02em;
    color:var(--text);
  }

  .vaPlan{
    display:inline-flex;
    align-items:center;
    min-height:24px;
    padding:5px 9px;
    border-radius:999px;
    border:1px solid var(--bd);
    background:color-mix(in oklab, var(--panel-bg) 78%, transparent);
    font:850 11px/1 system-ui;
    color:var(--text);
  }

  .vaAccountMeta,
  .vaSummaryLine{
    margin-top:6px;
    display:flex;
    flex-wrap:wrap;
    gap:8px 14px;
    color:var(--muted);
    font:650 12px/1.45 system-ui;
  }

  .vaStatusStack{
    display:flex;
    flex-wrap:wrap;
    gap:8px;
  }

  .vaBadge{
    display:inline-flex;
    align-items:center;
    min-height:28px;
    padding:6px 10px;
    border-radius:999px;
    font:800 12px/1 system-ui;
    border:1px solid var(--bd);
    white-space:nowrap;
  }

  .vaBadge.is-on{
    background:color-mix(in oklab, #10b981 12%, var(--card-bg));
    border-color:color-mix(in oklab, #10b981 34%, var(--bd));
    color:color-mix(in oklab, #065f46 78%, var(--text));
  }

  .vaBadge.is-off{
    background:color-mix(in oklab, #ef4444 10%, var(--card-bg));
    border-color:color-mix(in oklab, #ef4444 32%, var(--bd));
    color:color-mix(in oklab, #991b1b 72%, var(--text));
  }

  .vaPagination{
    display:flex;
    justify-content:center;
    padding-top:4px;
  }

  .vaModal{
    position:fixed;
    inset:0;
    display:none;
    align-items:center;
    justify-content:center;
    z-index:1900;
    padding:18px;
  }

  .vaModal.is-open{
    display:flex;
  }

  .vaModalBackdrop{
    position:absolute;
    inset:0;
    background:rgba(15,23,42,.46);
    backdrop-filter:blur(3px);
  }

  .vaModalDialog{
    position:relative;
    z-index:1;
    width:min(720px, calc(100vw - 24px));
    max-height:calc(100vh - 36px);
    overflow:auto;
    border:1px solid var(--bd);
    background:var(--card-bg);
    border-radius:22px;
    box-shadow:0 28px 70px rgba(15,23,42,.26);
  }

  .vaModalDialog--lg{
    width:min(1240px, calc(100vw - 24px));
  }

  .vaModalDialog--xl{
    width:min(1320px, calc(100vw - 24px));
  }

  .vaModalHead{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    padding:18px 18px 14px;
    border-bottom:1px solid var(--bd);
    position:sticky;
    top:0;
    background:color-mix(in oklab, var(--card-bg) 94%, transparent);
    backdrop-filter:blur(10px);
    z-index:2;
  }

  .vaModalKicker{
    font:900 10px/1 system-ui;
    letter-spacing:.12em;
    color:var(--muted);
  }

  .vaModalTitle{
    margin:6px 0 0;
    font:900 22px/1.08 system-ui;
    letter-spacing:-.02em;
    color:var(--text);
  }

  .vaModalSub{
    margin-top:6px;
    color:var(--muted);
    font:650 13px/1.45 system-ui;
  }

  .vaIconBtn{
    width:38px;
    height:38px;
    min-width:38px;
    border-radius:12px;
    border:1px solid var(--bd);
    background:color-mix(in oklab, var(--panel-bg) 82%, transparent);
    color:var(--text);
    font:900 20px/1 system-ui;
    cursor:pointer;
  }

  .vaModalBody{
    padding:18px;
    display:grid;
    gap:14px;
  }

  .vaModalFacts,
  .vaFormGrid{
    display:grid;
    grid-template-columns:repeat(3, minmax(0, 1fr));
    gap:12px;
  }

  .vaFact{
    border:1px solid var(--bd);
    border-radius:14px;
    padding:12px 13px;
    background:color-mix(in oklab, var(--panel-bg) 74%, transparent);
    display:grid;
    gap:6px;
    min-width:0;
  }

  .vaFact .k{
    font:750 11px/1 system-ui;
    color:var(--muted);
  }

  .vaFact .v{
    font:900 13px/1.15 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
    color:var(--text);
    overflow:hidden;
    text-overflow:ellipsis;
  }

  .vaChecksGrid,
  .vaPermissionRow{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(170px, 1fr));
    gap:10px 12px;
    align-items:start;
  }

  .vaModalFoot{
    padding:0 18px 18px;
    justify-content:flex-end;
  }

  .vaUsersCards{
    display:grid;
    gap:12px;
  }

  .vaUserCard{
    border:1px solid var(--bd);
    border-radius:16px;
    background:color-mix(in oklab, var(--panel-bg) 70%, transparent);
    padding:14px;
  }

  .vaUserCardMain{
    display:grid;
    gap:12px;
  }

  .vaUserName{
    font:800 13px/1.2 system-ui;
    color:var(--text);
  }

  .vaUserRole{
    margin-top:4px;
    color:var(--muted);
    font:650 12px/1.35 system-ui;
  }

  .vaCheck{
    display:flex;
    align-items:flex-start;
    gap:8px;
    min-height:42px;
    padding:10px 12px;
    border:1px solid var(--bd);
    border-radius:12px;
    background:color-mix(in oklab, var(--panel-bg) 72%, transparent);
    font:700 12px/1.25 system-ui;
    cursor:pointer;
    color:var(--text);
  }

  .vaCheck input[type="checkbox"]{
    width:16px;
    height:16px;
    margin-top:1px;
    flex:0 0 auto;
  }

  .vaDeleteInlineForm{
    display:inline-flex;
  }

  .vaMutedAction{
    color:var(--muted);
    font:700 12px/1 system-ui;
  }

  .vaNoUsersCard{
    border:1px dashed var(--bd);
    border-radius:16px;
    padding:18px;
    color:var(--muted);
    font:650 13px/1.45 system-ui;
    background:color-mix(in oklab, var(--panel-bg) 72%, transparent);
  }

  .vaChecksGrid{
    display:grid;
    grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));
    gap:10px 12px;
    align-items:start;
  }

  @media (max-width: 1180px){
    .vaListHead{
      display:none;
    }

    .vaRow{
      grid-template-columns:1fr;
      gap:10px;
      align-items:flex-start;
    }
  }

  @media (max-width: 920px){
    .vaSearchForm,
    .vaModalFacts,
    .vaFormGrid{
      grid-template-columns:1fr;
    }

    .vaHead,
    .vaModalHead{
      flex-direction:column;
      align-items:flex-start;
    }
  }

  @media (max-width: 640px){
    .vaActions,
    .vaSearchActions,
    .vaModalFoot,
    .vaUserActions,
    .vaModalHeadActions{
      flex-direction:column;
      align-items:stretch;
    }

    .vaBtn{
      width:100%;
    }

    .vaBtn--sm{
      width:auto;
    }

    .vaModal{
      padding:10px;
    }

    .vaModalDialog,
    .vaModalDialog--lg,
    .vaModalDialog--xl{
      width:100%;
      max-height:calc(100vh - 20px);
    }
  }
</style>
@endpush

@push('scripts')
<script>
(function () {
  'use strict';

  const body = document.body;
  const openButtons = document.querySelectorAll('[data-va-open]');
  const closeButtons = document.querySelectorAll('[data-va-close]');
  const modals = document.querySelectorAll('.vaModal');

  function openModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    modal.classList.add('is-open');
    modal.setAttribute('aria-hidden', 'false');
    body.style.overflow = 'hidden';
  }

  function closeModal(id) {
    const modal = document.getElementById(id);
    if (!modal) return;

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');

    const hasOpen = document.querySelector('.vaModal.is-open');
    if (!hasOpen) {
      body.style.overflow = '';
    }
  }

  openButtons.forEach((button) => {
    button.addEventListener('click', function () {
      const currentModal = this.getAttribute('data-va-close-current');
      const targetModal = this.getAttribute('data-va-open');

      if (currentModal) {
        closeModal(currentModal);
      }

      openModal(targetModal);
    });
  });

  closeButtons.forEach((button) => {
    button.addEventListener('click', function () {
      closeModal(this.getAttribute('data-va-close'));
    });
  });

  modals.forEach((modal) => {
    modal.addEventListener('click', function (event) {
      if (event.target === modal) {
        modal.classList.remove('is-open');
        modal.setAttribute('aria-hidden', 'true');
      }

      const hasOpen = document.querySelector('.vaModal.is-open');
      if (!hasOpen) {
        body.style.overflow = '';
      }
    });
  });

  document.addEventListener('keydown', function (event) {
    if (event.key !== 'Escape') return;

    const modal = document.querySelector('.vaModal.is-open');
    if (!modal) return;

    modal.classList.remove('is-open');
    modal.setAttribute('aria-hidden', 'true');

    const hasOpen = document.querySelector('.vaModal.is-open');
    if (!hasOpen) {
      body.style.overflow = '';
    }
  });
})();
</script>
@endpush