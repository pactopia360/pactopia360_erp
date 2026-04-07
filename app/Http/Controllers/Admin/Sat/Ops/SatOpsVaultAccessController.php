<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat\Ops;

use App\Http\Controllers\Controller;
use App\Models\Cliente\CuentaCliente;
use App\Models\Cliente\SatUserAccess;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

final class SatOpsVaultAccessController extends Controller
{
    private const CONN_CLIENTES = 'mysql_clientes';
    private const CONN_ADMIN    = 'mysql_admin';

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));

        $accounts = CuentaCliente::query()
            ->with(['usuarios' => function ($query) {
                $query->orderBy('nombre')->orderBy('email');
            }])
            ->when($q !== '', function ($query) use ($q) {
                $query->where(function ($sub) use ($q) {
                    $sub->where('id', 'like', '%' . $q . '%')
                        ->orWhere('rfc_padre', 'like', '%' . $q . '%')
                        ->orWhere('razon_social', 'like', '%' . $q . '%')
                        ->orWhere('nombre_comercial', 'like', '%' . $q . '%')
                        ->orWhere('email', 'like', '%' . $q . '%')
                        ->orWhere('codigo_cliente', 'like', '%' . $q . '%');
                });
            })
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
            return redirect()
                ->route('admin.sat.ops.vault_access.index')
                ->with('error', 'La cuenta solicitada no existe.');
        }

        if (!Schema::connection(self::CONN_CLIENTES)->hasColumn('cuentas_cliente', 'vault_active')) {
            return redirect()
                ->route('admin.sat.ops.vault_access.index', ['q' => $request->query('q', '')])
                ->with('error', 'La columna cuentas_cliente.vault_active no existe en esta base.');
        }

        $enabled = (int) $request->input('enabled') === 1;

        $account->vault_active = $enabled ? 1 : 0;
        $account->save();

        return redirect()
            ->route('admin.sat.ops.vault_access.index', ['q' => $request->query('q', '')])
            ->with('success', 'Acceso a Bóveda v1 actualizado para la cuenta.');
    }

    public function updateV2Module(Request $request, string $cuentaId): RedirectResponse
    {
        $request->validate([
            'enabled' => ['required', 'in:0,1'],
        ]);

        $account = CuentaCliente::query()->where('id', $cuentaId)->first();

        if (!$account) {
            return redirect()
                ->route('admin.sat.ops.vault_access.index')
                ->with('error', 'La cuenta solicitada no existe.');
        }

        $adminAccountId = (int) ($account->admin_account_id ?? 0);
        if ($adminAccountId <= 0) {
            return redirect()
                ->route('admin.sat.ops.vault_access.index', ['q' => $request->query('q', '')])
                ->with('error', 'La cuenta no tiene admin_account_id vinculado.');
        }

        if (!Schema::connection(self::CONN_ADMIN)->hasTable('accounts')) {
            return redirect()
                ->route('admin.sat.ops.vault_access.index', ['q' => $request->query('q', '')])
                ->with('error', 'La tabla accounts no existe en mysql_admin.');
        }

        if (!Schema::connection(self::CONN_ADMIN)->hasColumn('accounts', 'meta')) {
            return redirect()
                ->route('admin.sat.ops.vault_access.index', ['q' => $request->query('q', '')])
                ->with('error', 'La columna accounts.meta no existe en mysql_admin.');
        }

        $row = DB::connection(self::CONN_ADMIN)
            ->table('accounts')
            ->where('id', $adminAccountId)
            ->first();

        if (!$row) {
            return redirect()
                ->route('admin.sat.ops.vault_access.index', ['q' => $request->query('q', '')])
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

        return redirect()
            ->route('admin.sat.ops.vault_access.index', ['q' => $request->query('q', '')])
            ->with('success', 'Módulo SAT Bóveda v2 actualizado a nivel cuenta.');
    }

    public function updateV2Users(Request $request, string $cuentaId): RedirectResponse
    {
        $account = CuentaCliente::query()
            ->with('usuarios')
            ->where('id', $cuentaId)
            ->first();

        if (!$account) {
            return redirect()
                ->route('admin.sat.ops.vault_access.index')
                ->with('error', 'La cuenta solicitada no existe.');
        }

        $inputUsers = (array) $request->input('users', []);
        $adminId = (string) (Auth::guard('admin')->id() ?? '');

        foreach ($account->usuarios as $user) {
            $row = (array) ($inputUsers[$user->id] ?? []);

            $canAccessVault = isset($row['can_access_vault']) && (string) $row['can_access_vault'] === '1';

            $canUploadMetadata = $canAccessVault
                ? (isset($row['can_upload_metadata']) && (string) $row['can_upload_metadata'] === '1')
                : false;

            $canUploadXml = $canAccessVault
                ? (isset($row['can_upload_xml']) && (string) $row['can_upload_xml'] === '1')
                : false;

            $canExport = $canAccessVault
                ? (isset($row['can_export']) && (string) $row['can_export'] === '1')
                : false;

            SatUserAccess::query()->updateOrCreate(
                [
                    'cuenta_id'  => (string) $account->id,
                    'usuario_id' => (string) $user->id,
                ],
                [
                    'can_access_vault'    => $canAccessVault,
                    'can_upload_metadata' => $canUploadMetadata,
                    'can_upload_xml'      => $canUploadXml,
                    'can_export'          => $canExport,
                    'meta'                => [
                        'updated_from'    => 'admin_sat_ops_vault_access',
                        'updated_by'      => $adminId,
                        'updated_at'      => now()->toDateTimeString(),
                        'usuario_email'   => (string) ($user->email ?? ''),
                        'usuario_nombre'  => (string) ($user->nombre ?? ''),
                    ],
                ]
            );
        }

        return redirect()
            ->route('admin.sat.ops.vault_access.index', ['q' => $request->query('q', '')])
            ->with('success', 'Permisos de SAT Bóveda v2 actualizados para los usuarios de la cuenta.');
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