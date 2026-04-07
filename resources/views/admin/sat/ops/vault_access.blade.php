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
        Administra Bóveda v1 por cuenta y Bóveda v2 por usuario, respetando la arquitectura actual.
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

    <div class="vaSearchCard">
      <form method="GET" action="{{ route('admin.sat.ops.vault_access.index') }}" class="vaSearchForm">
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
          <a href="{{ route('admin.sat.ops.vault_access.index') }}" class="vaBtn vaBtn--ghost">Limpiar</a>
        </div>
      </form>
    </div>

    @if($accounts->count() === 0)
      <div class="vaEmpty">
        <div class="vaEmptyTitle">No se encontraron cuentas</div>
        <div class="vaEmptyText">Prueba con RFC, razón social, email o código de cliente.</div>
      </div>
    @endif

    <div class="vaList">
      @foreach($accounts as $account)
        @php
          $module = $moduleMap[$account->id] ?? ['enabled' => false, 'state' => 'inactive', 'admin_account_id' => 0];
          $usersAccess = $accessMap[$account->id] ?? [];
          $vaultV1Enabled = (int)($account->vault_active ?? 0) === 1;
          $quotaBytes = (int)($account->vault_quota_bytes ?? 0);
          $planLabel = strtoupper((string)($account->plan_actual ?? $account->plan ?? 'FREE'));
        @endphp

        <section class="vaCard">
          <div class="vaCardTop">
            <div class="vaIdentity">
              <div class="vaIdentityTop">
                <h2>{{ $account->razon_social ?: ($account->nombre_comercial ?: 'Cuenta sin nombre') }}</h2>
                <span class="vaPill">{{ $planLabel }}</span>
              </div>

              <div class="vaMeta">
                <span><strong>Cuenta:</strong> {{ $account->id }}</span>
                <span><strong>RFC:</strong> {{ $account->rfc_padre ?: '—' }}</span>
                <span><strong>Email:</strong> {{ $account->email ?: '—' }}</span>
                <span><strong>Admin account:</strong> {{ (int)($account->admin_account_id ?? 0) > 0 ? $account->admin_account_id : '—' }}</span>
              </div>
            </div>
          </div>

          <div class="vaGrids">
            <div class="vaBlock">
              <div class="vaBlockHead">
                <div>
                  <div class="vaBlockTitle">Bóveda v1 · Acceso por cuenta</div>
                  <div class="vaBlockSub">Controla el acceso general de la cuenta cliente a la bóveda tradicional.</div>
                </div>
                <span class="vaStatus {{ $vaultV1Enabled ? 'is-on' : 'is-off' }}">
                  {{ $vaultV1Enabled ? 'Activa' : 'Inactiva' }}
                </span>
              </div>

              <div class="vaFacts">
                <div class="vaFact">
                  <span class="k">vault_active</span>
                  <span class="v">{{ $vaultV1Enabled ? '1' : '0' }}</span>
                </div>
                <div class="vaFact">
                  <span class="k">vault_quota_bytes</span>
                  <span class="v">{{ number_format($quotaBytes) }}</span>
                </div>
              </div>

              <form method="POST" action="{{ route('admin.sat.ops.vault_access.v1.update', ['cuentaId' => $account->id, 'q' => $q]) }}" class="vaInlineForm">
                @csrf
                <input type="hidden" name="enabled" value="{{ $vaultV1Enabled ? '0' : '1' }}">
                <button type="submit" class="vaBtn {{ $vaultV1Enabled ? 'vaBtn--danger' : 'vaBtn--primary' }}">
                  {{ $vaultV1Enabled ? 'Desactivar v1' : 'Activar v1' }}
                </button>
              </form>
            </div>

            <div class="vaBlock">
              <div class="vaBlockHead">
                <div>
                  <div class="vaBlockTitle">SAT Bóveda v2 · Módulo de cuenta</div>
                  <div class="vaBlockSub">Primero debe estar activo el módulo en la cuenta admin; luego se habilita por usuario.</div>
                </div>
                <span class="vaStatus {{ $module['enabled'] ? 'is-on' : 'is-off' }}">
                  {{ $module['enabled'] ? 'Activo' : 'Inactivo' }}
                </span>
              </div>

              <div class="vaFacts">
                <div class="vaFact">
                  <span class="k">modules_state.sat_boveda_v2</span>
                  <span class="v">{{ $module['state'] ?: 'inactive' }}</span>
                </div>
                <div class="vaFact">
                  <span class="k">admin_account_id</span>
                  <span class="v">{{ $module['admin_account_id'] > 0 ? $module['admin_account_id'] : '—' }}</span>
                </div>
              </div>

              <form method="POST" action="{{ route('admin.sat.ops.vault_access.v2_module.update', ['cuentaId' => $account->id, 'q' => $q]) }}" class="vaInlineForm">
                @csrf
                <input type="hidden" name="enabled" value="{{ $module['enabled'] ? '0' : '1' }}">
                <button type="submit" class="vaBtn {{ $module['enabled'] ? 'vaBtn--danger' : 'vaBtn--primary' }}">
                  {{ $module['enabled'] ? 'Desactivar módulo v2' : 'Activar módulo v2' }}
                </button>
              </form>
            </div>
          </div>

          <div class="vaUsersBlock">
            <div class="vaBlockHead">
              <div>
                <div class="vaBlockTitle">SAT Bóveda v2 · Permisos por usuario</div>
                <div class="vaBlockSub">Estos permisos alimentan la tabla <code>sat_user_access</code>.</div>
              </div>
            </div>

            <form method="POST" action="{{ route('admin.sat.ops.vault_access.v2_users.update', ['cuentaId' => $account->id, 'q' => $q]) }}">
              @csrf

              <div class="vaUsersTableWrap">
                <table class="vaUsersTable">
                  <thead>
                    <tr>
                      <th>Usuario</th>
                      <th>Email</th>
                      <th>Acceso v2</th>
                      <th>Metadata</th>
                      <th>XML</th>
                      <th>Exportar</th>
                    </tr>
                  </thead>
                  <tbody>
                    @forelse($account->usuarios as $user)
                      @php
                        $ua = $usersAccess[$user->id] ?? null;
                      @endphp
                      <tr>
                        <td>
                          <div class="vaUserName">{{ $user->nombre ?: 'Usuario sin nombre' }}</div>
                          <div class="vaUserRole">
                            {{ $user->rol ?: '—' }}
                            @if(method_exists($user, 'isOwner') && $user->isOwner())
                              · owner
                            @endif
                          </div>
                        </td>
                        <td>{{ $user->email ?: '—' }}</td>

                        <td>
                          <label class="vaCheck">
                            <input type="hidden" name="users[{{ $user->id }}][can_access_vault]" value="0">
                            <input type="checkbox" name="users[{{ $user->id }}][can_access_vault]" value="1" {{ ($ua && $ua->can_access_vault) ? 'checked' : '' }}>
                            <span>Permitir</span>
                          </label>
                        </td>

                        <td>
                          <label class="vaCheck">
                            <input type="hidden" name="users[{{ $user->id }}][can_upload_metadata]" value="0">
                            <input type="checkbox" name="users[{{ $user->id }}][can_upload_metadata]" value="1" {{ ($ua && $ua->can_upload_metadata) ? 'checked' : '' }}>
                            <span>Metadata</span>
                          </label>
                        </td>

                        <td>
                          <label class="vaCheck">
                            <input type="hidden" name="users[{{ $user->id }}][can_upload_xml]" value="0">
                            <input type="checkbox" name="users[{{ $user->id }}][can_upload_xml]" value="1" {{ ($ua && $ua->can_upload_xml) ? 'checked' : '' }}>
                            <span>XML</span>
                          </label>
                        </td>

                        <td>
                          <label class="vaCheck">
                            <input type="hidden" name="users[{{ $user->id }}][can_export]" value="0">
                            <input type="checkbox" name="users[{{ $user->id }}][can_export]" value="1" {{ ($ua && $ua->can_export) ? 'checked' : '' }}>
                            <span>Exportar</span>
                          </label>
                        </td>
                      </tr>
                    @empty
                      <tr>
                        <td colspan="6">
                          <div class="vaNoUsers">Esta cuenta no tiene usuarios cargados.</div>
                        </td>
                      </tr>
                    @endforelse
                  </tbody>
                </table>
              </div>

              @if($account->usuarios->count() > 0)
                <div class="vaUsersActions">
                  <button type="submit" class="vaBtn vaBtn--primary">Guardar permisos v2</button>
                </div>
              @endif
            </form>
          </div>
        </section>
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
  .page-admin-sat-vault-access .page-container{ padding-top: 14px; }

  .vaWrap{ display:grid; gap:16px; }

  .vaHead{
    display:flex;
    justify-content:space-between;
    align-items:flex-end;
    gap:14px;
    padding:14px 16px;
  }
  .vaKicker{
    font:900 11px/1 system-ui;
    letter-spacing:.12em;
    color:var(--muted);
  }
  .vaTitle{
    margin:6px 0 0;
    font:950 24px/1.1 system-ui;
    letter-spacing:-.02em;
  }
  .vaSub{
    margin-top:6px;
    color:var(--muted);
    font:650 13px/1.45 system-ui;
  }

  .vaSearchCard,
  .vaCard{
    border:1px solid var(--bd);
    background:var(--card-bg);
    border-radius:18px;
    box-shadow:var(--shadow-1);
  }

  .vaSearchCard{ padding:16px; }

  .vaSearchForm{
    display:grid;
    grid-template-columns:minmax(260px, 1fr) auto;
    gap:12px;
    align-items:end;
  }
  .vaField{ display:grid; gap:8px; }
  .vaField label{
    font:800 12px/1 system-ui;
    color:var(--muted);
  }
  .vaField input{
    width:100%;
    min-height:44px;
    border:1px solid var(--bd);
    border-radius:12px;
    background:color-mix(in oklab, var(--panel-bg) 78%, transparent);
    color:var(--text);
    padding:12px 14px;
    outline:none;
  }
  .vaField input:focus{
    border-color:color-mix(in oklab, #8b5cf6 40%, var(--bd));
    box-shadow:0 0 0 4px color-mix(in oklab, #8b5cf6 14%, transparent);
  }

  .vaSearchActions,
  .vaHeadActions,
  .vaInlineForm,
  .vaUsersActions{
    display:flex;
    gap:10px;
    flex-wrap:wrap;
  }

  .vaBtn{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    min-height:42px;
    padding:10px 14px;
    border-radius:12px;
    border:1px solid var(--bd);
    text-decoration:none;
    cursor:pointer;
    font:850 13px/1 system-ui;
    color:var(--text);
    background:color-mix(in oklab, var(--card-bg) 88%, transparent);
  }
  .vaBtn:hover{ filter:brightness(.98); }
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

  .vaAlert{
    padding:12px 14px;
    border-radius:14px;
    border:1px solid var(--bd);
    font:700 13px/1.4 system-ui;
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
    border:1px dashed var(--bd);
    border-radius:18px;
    background:color-mix(in oklab, var(--panel-bg) 70%, transparent);
  }
  .vaEmptyTitle{
    font:900 16px/1 system-ui;
    margin-bottom:8px;
  }
  .vaEmptyText{
    color:var(--muted);
    font:650 13px/1.5 system-ui;
  }

  .vaList{ display:grid; gap:16px; }

  .vaCard{ padding:16px; }

  .vaCardTop{
    display:flex;
    justify-content:space-between;
    gap:14px;
    margin-bottom:14px;
  }

  .vaIdentityTop{
    display:flex;
    align-items:center;
    gap:10px;
    flex-wrap:wrap;
  }
  .vaIdentityTop h2{
    margin:0;
    font:900 18px/1.1 system-ui;
  }

  .vaPill{
    display:inline-flex;
    align-items:center;
    min-height:28px;
    padding:6px 10px;
    border-radius:999px;
    border:1px solid var(--bd);
    background:color-mix(in oklab, var(--panel-bg) 72%, transparent);
    font:800 12px/1 system-ui;
  }

  .vaMeta{
    margin-top:8px;
    display:flex;
    flex-wrap:wrap;
    gap:10px 14px;
    color:var(--muted);
    font:650 12px/1.45 system-ui;
  }

  .vaGrids{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:14px;
    margin-bottom:14px;
  }

  .vaBlock,
  .vaUsersBlock{
    border:1px solid var(--bd);
    border-radius:16px;
    background:color-mix(in oklab, var(--card-bg) 94%, transparent);
    padding:14px;
  }

  .vaBlockHead{
    display:flex;
    justify-content:space-between;
    align-items:flex-start;
    gap:12px;
    margin-bottom:12px;
  }

  .vaBlockTitle{
    font:900 14px/1.15 system-ui;
  }
  .vaBlockSub{
    margin-top:5px;
    color:var(--muted);
    font:650 12px/1.45 system-ui;
  }

  .vaStatus{
    display:inline-flex;
    align-items:center;
    min-height:28px;
    padding:6px 10px;
    border-radius:999px;
    font:850 12px/1 system-ui;
    border:1px solid var(--bd);
    white-space:nowrap;
  }
  .vaStatus.is-on{
    background:color-mix(in oklab, #10b981 12%, var(--card-bg));
    border-color:color-mix(in oklab, #10b981 35%, var(--bd));
  }
  .vaStatus.is-off{
    background:color-mix(in oklab, #ef4444 10%, var(--card-bg));
    border-color:color-mix(in oklab, #ef4444 35%, var(--bd));
  }

  .vaFacts{
    display:grid;
    grid-template-columns:repeat(2, minmax(0, 1fr));
    gap:10px;
    margin-bottom:14px;
  }
  .vaFact{
    border:1px solid var(--bd);
    border-radius:12px;
    padding:10px 12px;
    background:color-mix(in oklab, var(--panel-bg) 70%, transparent);
    display:grid;
    gap:6px;
  }
  .vaFact .k{
    font:750 11px/1 system-ui;
    color:var(--muted);
  }
  .vaFact .v{
    font:900 13px/1.1 ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", "Courier New", monospace;
  }

  .vaUsersTableWrap{
    overflow:auto;
    border:1px solid var(--bd);
    border-radius:14px;
  }

  .vaUsersTable{
    width:100%;
    border-collapse:collapse;
    min-width:860px;
    background:var(--card-bg);
  }
  .vaUsersTable th,
  .vaUsersTable td{
    padding:12px 12px;
    border-bottom:1px solid var(--bd);
    text-align:left;
    vertical-align:middle;
  }
  .vaUsersTable thead th{
    background:color-mix(in oklab, var(--panel-bg) 78%, transparent);
    font:850 12px/1 system-ui;
    color:var(--muted);
  }
  .vaUsersTable tbody tr:hover{
    background:color-mix(in oklab, var(--panel-bg) 50%, transparent);
  }

  .vaUserName{
    font:800 13px/1.2 system-ui;
  }
  .vaUserRole{
    margin-top:4px;
    color:var(--muted);
    font:650 12px/1.35 system-ui;
  }

  .vaCheck{
    display:inline-flex;
    align-items:center;
    gap:8px;
    font:700 12px/1 system-ui;
    cursor:pointer;
  }
  .vaCheck input[type="checkbox"]{
    width:16px;
    height:16px;
  }

  .vaNoUsers{
    padding:10px 0;
    color:var(--muted);
    font:650 13px/1.4 system-ui;
  }

  .vaUsersActions{
    margin-top:14px;
    justify-content:flex-end;
  }

  .vaPagination{
    display:flex;
    justify-content:center;
    padding-top:6px;
  }

  @media (max-width: 1100px){
    .vaGrids{
      grid-template-columns:1fr;
    }
  }

  @media (max-width: 860px){
    .vaSearchForm{
      grid-template-columns:1fr;
    }
    .vaHead{
      flex-direction:column;
      align-items:flex-start;
    }
  }
</style>
@endpush