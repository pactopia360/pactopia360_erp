<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat\Ops;

use App\Http\Controllers\Controller;
use App\Models\Cliente\CuentaCliente;
use App\Models\Cliente\SatUserAccess;
use App\Models\Cliente\UsuarioCuenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class SatOpsVaultAccessController extends Controller
{
    private const CONN_CLIENTES = 'mysql_clientes';
    private const CONN_ADMIN    = 'mysql_admin';
    private const ROUTE_INDEX   = 'admin.billing.vault_access.index';

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $accountsQuery = CuentaCliente::query()
            ->with(['usuarios' => function ($query) {
                $query->orderBy('nombre')->orderBy('email');
            }]);

        /*
        |--------------------------------------------------------------------------
        | Ocultar cuentas basura / demo / duplicadas visibles en este módulo
        |--------------------------------------------------------------------------
        | No se borran de BD, solo se excluyen del listado administrativo.
        */
        $accountsQuery->where(function ($query) {
            // Excluir registros marcados explícitamente como duplicados
            $query->whereNull('razon_social')
                ->orWhere('razon_social', 'not like', '[DUPLICATE]%');
        });

        $accountsQuery->where(function ($query) {
            // Excluir nombres genéricos tipo "Cuenta 9", "Cuenta 14", etc.
            $query->whereNull('razon_social')
                ->orWhere('razon_social', 'not regexp', '^Cuenta[[:space:]]+[0-9]+$');
        });

        $accountsQuery->where(function ($query) {
            $query->whereNull('nombre_comercial')
                ->orWhere('nombre_comercial', 'not regexp', '^Cuenta[[:space:]]+[0-9]+$');
        });

        $accountsQuery->where(function ($query) {
            // Excluir correos de pruebas internas
            $query->whereNull('email')
                ->orWhere('email', 'not like', '%@pactopia.test')
                ->orWhere('email', 'not like', '%@example.com');
        });

        if ($q !== '') {
            $searchableColumns = $this->getSearchableAccountColumns();

            if (!empty($searchableColumns)) {
                $accountsQuery->where(function ($sub) use ($q, $searchableColumns) {
                    foreach ($searchableColumns as $index => $column) {
                        if ($index === 0) {
                            $sub->where($column, 'like', '%' . $q . '%');
                        } else {
                            $sub->orWhere($column, 'like', '%' . $q . '%');
                        }
                    }
                });
            }
        }

        $accounts = $accountsQuery
            ->orderByRaw("
                CASE
                    WHEN plan_actual = 'PRO' THEN 0
                    WHEN plan_actual = 'BASIC' THEN 1
                    WHEN plan_actual = 'FREE' THEN 2
                    WHEN plan_actual IS NULL THEN 3
                    ELSE 4
                END
            ")
            ->orderBy('razon_social')
            ->orderBy('nombre_comercial')
            ->paginate(12)
            ->withQueryString();

        $accessMap = $this->buildAccessMap($accounts);
        $moduleMap = $this->buildModuleMap($accounts);

        return view('admin.sat.ops.vault_access', [
            'accounts'  => $accounts,
            'q'         => $q,
            'accessMap' => $accessMap,
            'moduleMap' => $moduleMap,
        ]);
    }

    public function updateV1(Request $request, string $cuentaId): RedirectResponse
    {
        $request->validate([
            'enabled' => ['required', 'in:0,1'],
        ]);

        $account = CuentaCliente::query()->where('id', $cuentaId)->first();

        if (!$account) {
            return $this->redirectToIndex($request)
                ->with('error', 'La cuenta solicitada no existe.');
        }

        if (!Schema::connection(self::CONN_CLIENTES)->hasColumn('cuentas_cliente', 'vault_active')) {
            return $this->redirectToIndex($request)
                ->with('error', 'La columna cuentas_cliente.vault_active no existe en esta base.');
        }

        $enabled = (int) $request->input('enabled') === 1;

        $account->vault_active = $enabled ? 1 : 0;
        $account->save();

        return $this->redirectToIndex($request)
            ->with('success', 'Acceso a Bóveda v1 actualizado para la cuenta.');
    }

    public function updateV2Module(Request $request, string $cuentaId): RedirectResponse
    {
        $request->validate([
            'enabled' => ['required', 'in:0,1'],
        ]);

        $account = CuentaCliente::query()->where('id', $cuentaId)->first();

        if (!$account) {
            return $this->redirectToIndex($request)
                ->with('error', 'La cuenta solicitada no existe.');
        }

        $adminAccountId = (int) ($account->admin_account_id ?? 0);
        if ($adminAccountId <= 0) {
            return $this->redirectToIndex($request)
                ->with('error', 'La cuenta no tiene admin_account_id vinculado.');
        }

        if (!Schema::connection(self::CONN_ADMIN)->hasTable('accounts')) {
            return $this->redirectToIndex($request)
                ->with('error', 'La tabla accounts no existe en mysql_admin.');
        }

        if (!Schema::connection(self::CONN_ADMIN)->hasColumn('accounts', 'meta')) {
            return $this->redirectToIndex($request)
                ->with('error', 'La columna accounts.meta no existe en mysql_admin.');
        }

        $row = DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where('id', $adminAccountId)
            ->first();

        if (!$row) {
            return $this->redirectToIndex($request)
                ->with('error', 'La cuenta admin vinculada no existe.');
        }

        $enabled = (int) $request->input('enabled') === 1;
        $meta = $this->decodeMeta($row->meta ?? null);

        data_set($meta, 'modules.sat_boveda_v2', $enabled);
        data_set($meta, 'modules_state.sat_boveda_v2', $enabled ? 'active' : 'inactive');
        data_set($meta, 'modules_updated_at.sat_boveda_v2', now()->toDateTimeString());
        data_set($meta, 'modules_updated_by.sat_boveda_v2', (string) (Auth::guard('admin')->id() ?? ''));

        DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where('id', $adminAccountId)
            ->update([
                'meta'       => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);

        return $this->redirectToIndex($request)
            ->with('success', 'Módulo SAT Bóveda v2 actualizado a nivel cuenta.');
    }

    public function updateV2Users(Request $request, string $cuentaId): RedirectResponse
    {
        $account = CuentaCliente::query()
            ->with('usuarios')
            ->where('id', $cuentaId)
            ->first();

        if (!$account) {
            return $this->redirectToIndex($request)
                ->with('error', 'La cuenta solicitada no existe.');
        }

        $inputUsers = (array) $request->input('users', []);
        $adminId = (string) (Auth::guard('admin')->id() ?? '');

        foreach ($account->usuarios as $user) {
            $row = (array) ($inputUsers[$user->id] ?? []);

            $canAccessV1 = isset($row['can_access_v1']) && (string) $row['can_access_v1'] === '1';
            $canAccessV2 = isset($row['can_access_v2']) && (string) $row['can_access_v2'] === '1';

            // Compatibilidad con versión anterior del formulario
            if (!$canAccessV2 && isset($row['can_access_vault']) && (string) $row['can_access_vault'] === '1') {
                $canAccessV2 = true;
            }

            $canUploadMetadata = $canAccessV2
                ? (isset($row['can_upload_metadata']) && (string) $row['can_upload_metadata'] === '1')
                : false;

            $canUploadXml = $canAccessV2
                ? (isset($row['can_upload_xml']) && (string) $row['can_upload_xml'] === '1')
                : false;

            $canExport = $canAccessV2
                ? (isset($row['can_export']) && (string) $row['can_export'] === '1')
                : false;

            $this->syncUserVaultAccess(
                cuentaId: (string) $account->id,
                usuarioId: (string) $user->id,
                usuarioNombre: (string) ($user->nombre ?? ''),
                usuarioEmail: (string) ($user->email ?? ''),
                adminId: $adminId,
                canAccessV1: $canAccessV1,
                canAccessV2: $canAccessV2,
                canUploadMetadata: $canUploadMetadata,
                canUploadXml: $canUploadXml,
                canExport: $canExport,
            );
        }

        return $this->redirectToIndex($request)
            ->with('success', 'Permisos de Bóveda V1 y Bóveda V2 actualizados para los usuarios de la cuenta.');
    }

    public function storeAccountUser(Request $request, string $cuentaId): RedirectResponse
    {
        $account = CuentaCliente::query()
            ->withCount('usuarios')
            ->where('id', $cuentaId)
            ->first();

        if (!$account) {
            return $this->redirectToIndex($request)
                ->with('error', 'La cuenta solicitada no existe.');
        }

        $validated = $request->validate([
            'nombre'              => ['required', 'string', 'max:160'],
            'email'               => [
                'required',
                'string',
                'email:rfc',
                'max:190',
                Rule::unique(self::CONN_CLIENTES . '.usuarios_cuenta', 'email'),
            ],
            'rol'                 => ['nullable', 'string', 'max:80'],
            'tipo'                => ['nullable', 'string', 'max:80'],
            'activo'              => ['nullable', 'in:0,1'],
            'must_change_password'=> ['nullable', 'in:0,1'],
            'password'            => ['nullable', 'string', 'min:8', 'max:120'],
            'can_access_v1'       => ['nullable', 'in:0,1'],
            'can_access_v2'       => ['nullable', 'in:0,1'],
            'can_access_vault'    => ['nullable', 'in:0,1'],
            'can_upload_metadata' => ['nullable', 'in:0,1'],
            'can_upload_xml'      => ['nullable', 'in:0,1'],
            'can_export'          => ['nullable', 'in:0,1'],
        ]);

        $maxUsuarios = (int) ($account->max_usuarios ?? 0);
        if ($maxUsuarios > 0 && (int) $account->usuarios_count >= $maxUsuarios) {
            return $this->redirectToIndex($request)
                ->with('error', 'La cuenta ya alcanzó el máximo de usuarios permitidos.');
        }

        $plainPassword = trim((string) ($validated['password'] ?? ''));
        if ($plainPassword === '') {
            $plainPassword = Str::random(12);
        }

        $user = new UsuarioCuenta();
        $user->cuenta_id = (string) $account->id;
        $user->nombre = trim((string) $validated['nombre']);
        $user->email = Str::lower(trim((string) $validated['email']));
        $user->rol = trim((string) ($validated['rol'] ?? 'usuario')) ?: 'usuario';
        $user->tipo = trim((string) ($validated['tipo'] ?? 'usuario')) ?: 'usuario';
        $user->activo = ((string) ($validated['activo'] ?? '1')) === '1';
        $user->must_change_password = ((string) ($validated['must_change_password'] ?? '1')) === '1';
        $user->password = $plainPassword;
        $user->password_temp = $plainPassword;
        $user->save();

        $canAccessV2 = ((string) ($validated['can_access_v2'] ?? '0')) === '1';
        if (!$canAccessV2 && ((string) ($validated['can_access_vault'] ?? '0')) === '1') {
            $canAccessV2 = true;
        }

        $this->syncUserVaultAccess(
            cuentaId: (string) $account->id,
            usuarioId: (string) $user->id,
            usuarioNombre: (string) $user->nombre,
            usuarioEmail: (string) $user->email,
            adminId: (string) (Auth::guard('admin')->id() ?? ''),
            canAccessV1: ((string) ($validated['can_access_v1'] ?? '0')) === '1',
            canAccessV2: $canAccessV2,
            canUploadMetadata: ((string) ($validated['can_upload_metadata'] ?? '0')) === '1',
            canUploadXml: ((string) ($validated['can_upload_xml'] ?? '0')) === '1',
            canExport: ((string) ($validated['can_export'] ?? '0')) === '1',
        );

        return $this->redirectToIndex($request)
            ->with('success', 'Usuario agregado correctamente a la cuenta.');
    }

    public function updateAccountUser(Request $request, string $cuentaId, string $usuarioId): RedirectResponse
    {
        $account = CuentaCliente::query()
            ->with('usuarios')
            ->where('id', $cuentaId)
            ->first();

        if (!$account) {
            return $this->redirectToIndex($request)
                ->with('error', 'La cuenta solicitada no existe.');
        }

        $user = UsuarioCuenta::query()
            ->where('cuenta_id', (string) $account->id)
            ->where('id', (string) $usuarioId)
            ->first();

        if (!$user) {
            return $this->redirectToIndex($request)
                ->with('error', 'El usuario solicitado no existe dentro de la cuenta.');
        }

        $validated = $request->validate([
            'nombre'              => ['required', 'string', 'max:160'],
            'email'               => [
                'required',
                'string',
                'email:rfc',
                'max:190',
                Rule::unique(self::CONN_CLIENTES . '.usuarios_cuenta', 'email')->ignore((string) $user->id, 'id'),
            ],
            'rol'                 => ['nullable', 'string', 'max:80'],
            'tipo'                => ['nullable', 'string', 'max:80'],
            'activo'              => ['nullable', 'in:0,1'],
            'must_change_password'=> ['nullable', 'in:0,1'],
            'password'            => ['nullable', 'string', 'min:8', 'max:120'],
            'can_access_v1'       => ['nullable', 'in:0,1'],
            'can_access_v2'       => ['nullable', 'in:0,1'],
            'can_access_vault'    => ['nullable', 'in:0,1'],
            'can_upload_metadata' => ['nullable', 'in:0,1'],
            'can_upload_xml'      => ['nullable', 'in:0,1'],
            'can_export'          => ['nullable', 'in:0,1'],
        ]);

        $plainPassword = trim((string) ($validated['password'] ?? ''));

        $user->nombre = trim((string) $validated['nombre']);
        $user->email = Str::lower(trim((string) $validated['email']));
        $user->rol = trim((string) ($validated['rol'] ?? $user->rol)) ?: 'usuario';
        $user->tipo = trim((string) ($validated['tipo'] ?? $user->tipo)) ?: 'usuario';
        $user->activo = ((string) ($validated['activo'] ?? '1')) === '1';
        $user->must_change_password = ((string) ($validated['must_change_password'] ?? '0')) === '1';

        if ($plainPassword !== '') {
            $user->password = $plainPassword;
            $user->password_temp = $plainPassword;
        }

        $user->save();

        $canAccessV2 = ((string) ($validated['can_access_v2'] ?? '0')) === '1';
        if (!$canAccessV2 && ((string) ($validated['can_access_vault'] ?? '0')) === '1') {
            $canAccessV2 = true;
        }

        $this->syncUserVaultAccess(
            cuentaId: (string) $account->id,
            usuarioId: (string) $user->id,
            usuarioNombre: (string) $user->nombre,
            usuarioEmail: (string) $user->email,
            adminId: (string) (Auth::guard('admin')->id() ?? ''),
            canAccessV1: ((string) ($validated['can_access_v1'] ?? '0')) === '1',
            canAccessV2: $canAccessV2,
            canUploadMetadata: ((string) ($validated['can_upload_metadata'] ?? '0')) === '1',
            canUploadXml: ((string) ($validated['can_upload_xml'] ?? '0')) === '1',
            canExport: ((string) ($validated['can_export'] ?? '0')) === '1',
        );

        return $this->redirectToIndex($request)
            ->with('success', 'Usuario actualizado correctamente.');
    }

    public function deleteV2UserAccess(Request $request, string $cuentaId, string $usuarioId): RedirectResponse
    {
        $account = CuentaCliente::query()
            ->with('usuarios')
            ->where('id', $cuentaId)
            ->first();

        if (!$account) {
            return $this->redirectToIndex($request)
                ->with('error', 'La cuenta solicitada no existe.');
        }

        $userExistsInAccount = $account->usuarios->contains(function ($user) use ($usuarioId) {
            return (string) $user->id === (string) $usuarioId;
        });

        if (!$userExistsInAccount) {
            return $this->redirectToIndex($request)
                ->with('error', 'El usuario no pertenece a la cuenta seleccionada.');
        }

        $deleted = SatUserAccess::query()
            ->where('cuenta_id', (string) $account->id)
            ->where('usuario_id', (string) $usuarioId)
            ->delete();

        if ($deleted <= 0) {
            return $this->redirectToIndex($request)
                ->with('error', 'El usuario no tenía accesos registrados para eliminar.');
        }

        return $this->redirectToIndex($request)
            ->with('success', 'Accesos del usuario a Bóveda V1 y Bóveda V2 eliminados correctamente.');
    }

    private function redirectToIndex(Request $request): RedirectResponse
    {
        $q = trim((string) $request->query('q', ''));

        return redirect()->route(
            self::ROUTE_INDEX,
            $q !== '' ? ['q' => $q] : []
        );
    }

    private function syncUserVaultAccess(
        string $cuentaId,
        string $usuarioId,
        string $usuarioNombre,
        string $usuarioEmail,
        string $adminId,
        bool $canAccessV1,
        bool $canAccessV2,
        bool $canUploadMetadata,
        bool $canUploadXml,
        bool $canExport
    ): void {
        $canUploadMetadata = $canAccessV2 ? $canUploadMetadata : false;
        $canUploadXml = $canAccessV2 ? $canUploadXml : false;
        $canExport = $canAccessV2 ? $canExport : false;

        if (!$canAccessV1 && !$canAccessV2 && !$canUploadMetadata && !$canUploadXml && !$canExport) {
            SatUserAccess::query()
                ->where('cuenta_id', $cuentaId)
                ->where('usuario_id', $usuarioId)
                ->delete();

            return;
        }

        $existing = SatUserAccess::query()
            ->where('cuenta_id', $cuentaId)
            ->where('usuario_id', $usuarioId)
            ->first();

        $existingMeta = $this->decodeMeta($existing?->meta);

        $meta = array_replace_recursive($existingMeta, [
            'updated_from'   => 'admin_sat_ops_vault_access',
            'updated_by'     => $adminId,
            'updated_at'     => now()->toDateTimeString(),
            'usuario_email'  => $usuarioEmail,
            'usuario_nombre' => $usuarioNombre,
            'vault_access'   => [
                'v1' => $canAccessV1,
                'v2' => $canAccessV2,
            ],
        ]);

        SatUserAccess::query()->updateOrCreate(
            [
                'cuenta_id'  => $cuentaId,
                'usuario_id' => $usuarioId,
            ],
            [
                // Se conserva este campo como compatibilidad para lógica previa
                'can_access_vault'    => $canAccessV2,
                'can_upload_metadata' => $canUploadMetadata,
                'can_upload_xml'      => $canUploadXml,
                'can_export'          => $canExport,
                'meta'                => $meta,
            ]
        );
    }

    /**
     * @param LengthAwarePaginator<int, CuentaCliente> $accounts
     * @return array<string, array<string, SatUserAccess>>
     */
    private function buildAccessMap(LengthAwarePaginator $accounts): array
    {
        $accountIds = collect($accounts->items())
            ->pluck('id')
            ->filter()
            ->values();

        if ($accountIds->isEmpty()) {
            return [];
        }

        $rows = SatUserAccess::query()
            ->whereIn('cuenta_id', $accountIds->all())
            ->get();

        $map = [];

        foreach ($rows as $row) {
            $cuentaId = (string) $row->cuenta_id;
            $usuarioId = (string) $row->usuario_id;

            if (!isset($map[$cuentaId])) {
                $map[$cuentaId] = [];
            }

            $map[$cuentaId][$usuarioId] = $row;
        }

        return $map;
    }

    /**
     * @param LengthAwarePaginator<int, CuentaCliente> $accounts
     * @return array<string, array{enabled: bool, state: string, admin_account_id: int}>
     */
    private function buildModuleMap(LengthAwarePaginator $accounts): array
    {
        $adminAccountIds = collect($accounts->items())
            ->pluck('admin_account_id')
            ->filter(fn ($id) => (int) $id > 0)
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();

        $rows = collect();

        if (
            !$adminAccountIds->isEmpty()
            && Schema::connection(self::CONN_ADMIN)->hasTable('accounts')
            && Schema::connection(self::CONN_ADMIN)->hasColumn('accounts', 'meta')
        ) {
            $rows = DB::connection(self::CONN_ADMIN)
                ->table('accounts')
                ->select(['id', 'meta'])
                ->whereIn('id', $adminAccountIds->all())
                ->get()
                ->keyBy('id');
        }

        $map = [];

        foreach ($accounts->items() as $account) {
            $adminAccountId = (int) ($account->admin_account_id ?? 0);
            $enabled = false;
            $state = 'inactive';

            if ($adminAccountId > 0 && isset($rows[$adminAccountId])) {
                $meta = $this->decodeMeta($rows[$adminAccountId]->meta ?? null);
                $stateValue = strtolower((string) data_get($meta, 'modules_state.sat_boveda_v2', ''));

                if ($stateValue !== '') {
                    $state = $stateValue;
                    $enabled = $stateValue === 'active';
                } else {
                    $enabled = (bool) data_get($meta, 'modules.sat_boveda_v2', false);
                    $state = $enabled ? 'active' : 'inactive';
                }
            }

            $map[(string) $account->id] = [
                'enabled'          => $enabled,
                'state'            => $state,
                'admin_account_id' => $adminAccountId,
            ];
        }

        return $map;
    }

    /**
     * @return array<int, string>
     */
    private function getSearchableAccountColumns(): array
    {
        $columns = [
            'id',
            'rfc_padre',
            'razon_social',
            'nombre_comercial',
            'email',
            'codigo_cliente',
        ];

        return array_values(array_filter($columns, function (string $column): bool {
            return Schema::connection(self::CONN_CLIENTES)->hasColumn('cuentas_cliente', $column);
        }));
    }

    /**
     * @param mixed $meta
     */
    private function getUserVaultAccessFlag(mixed $meta, string $key, bool $default = false): bool
    {
        $decoded = $this->decodeMeta($meta);
        return (bool) data_get($decoded, 'vault_access.' . $key, $default);
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeMeta(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_string($value) && trim($value) !== '') {
            $decoded = json_decode($value, true);
            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }
}