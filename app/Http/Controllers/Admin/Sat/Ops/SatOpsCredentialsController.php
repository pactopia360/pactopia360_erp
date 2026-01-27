<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin\Sat\Ops;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;


final class SatOpsCredentialsController extends Controller
{
    public function index(Request $request): View
    {
        $q      = trim((string) $request->query('q', ''));
        $status = strtolower(trim((string) $request->query('status', '')));
        $origin = strtolower(trim((string) $request->query('origin', '')));
        $per    = (int) $request->query('per', 25);

        if ($per < 10)  $per = 10;
        if ($per > 200) $per = 200;

        $page = max(1, (int) $request->query('page', 1));

        // =========================================================
        // Fuente: mysql_clientes.sat_credentials
        // =========================================================
        $connClientes   = DB::connection('mysql_clientes');
        $schemaClientes = Schema::connection('mysql_clientes');

        $hasColClientes = function (string $t, string $c) use ($schemaClientes): bool {
            try { return $schemaClientes->hasColumn($t, $c); } catch (\Throwable) { return false; }
        };

        $base = $connClientes->table('sat_credentials as sc');

        $select = [
            'sc.id',
            'sc.account_id',
            'sc.cuenta_id',
            'sc.rfc',
            'sc.razon_social',
            'sc.cer_path',
            'sc.key_path',
            'sc.key_password',
            'sc.key_password_enc',
            'sc.validated_at',
            'sc.auto_download',
            'sc.alert_email',
            'sc.alert_whatsapp',
            'sc.alert_inapp',
            'sc.last_alert_at',
            'sc.created_at',
            'sc.updated_at',
            'sc.meta',

            // Referencia "principal" para buscar cuenta (puede ser UUID o int)
            DB::raw("COALESCE(NULLIF(sc.account_id,''), sc.cuenta_id) as account_ref_id"),

            // flags
            DB::raw("CASE WHEN sc.validated_at IS NULL THEN 0 ELSE 1 END as is_valid"),
            DB::raw("CASE WHEN (sc.cer_path IS NOT NULL AND sc.cer_path <> '' AND sc.key_path IS NOT NULL AND sc.key_path <> '') THEN 1 ELSE 0 END as has_files"),

            // Bloqueado (meta)
            DB::raw("CASE
                WHEN JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.blocked')) IN ('1','true','TRUE') THEN 1
                WHEN JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.status')) IN ('blocked','error','invalid') THEN 1
                ELSE 0
            END as is_blocked"),

            // Externo
            DB::raw("NULLIF(JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.external_rfc')), '') as external_rfc"),
            DB::raw("CASE
                WHEN JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.from_external')) IN ('1','true','TRUE') THEN 1
                WHEN JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.is_external')) IN ('1','true','TRUE') THEN 1
                WHEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.external_rfc')), '') IS NOT NULL THEN 1
                ELSE 0
            END as from_external"),
            DB::raw("CASE
                WHEN JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.external_verified')) IN ('1','true','TRUE') THEN 1
                WHEN JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.verified')) IN ('1','true','TRUE') THEN 1
                ELSE 0
            END as external_verified"),

            // sat_status texto
            DB::raw("CASE
                WHEN NULLIF(JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.sat_status')), '') IS NOT NULL
                    THEN JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.sat_status'))
                WHEN sc.validated_at IS NOT NULL THEN 'validado'
                ELSE 'pendiente'
            END as sat_status"),

            // Auditoría (meta)
            DB::raw("NULLIF(JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.created_by')), '') as created_by"),
            DB::raw("NULLIF(JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.created_by_name')), '') as created_by_name"),
            DB::raw("NULLIF(JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.created_by_email')), '') as created_by_email"),

                        // Campos para UI (se rellenan después)
            DB::raw("NULL as account_name"),
            DB::raw("NULL as account_hint"),

            // Detalle de cuenta (drawer)
            DB::raw("NULL as account_email"),
            DB::raw("NULL as account_phone"),
            DB::raw("NULL as account_status"),
            DB::raw("NULL as account_plan"),
            DB::raw("NULL as account_created_at"),


        ];

        // compat: si no existe key_password_enc
        if (!$hasColClientes('sat_credentials', 'key_password_enc')) {
            $select = array_values(array_filter($select, fn($s) => $s !== 'sc.key_password_enc'));
            $select[] = DB::raw("NULL as key_password_enc");
        }

        $base->select($select);

        // =========================================================
        // Filtros
        // =========================================================
        if ($q !== '') {
            $base->where(function ($w) use ($q) {
                $w->where('sc.rfc', 'like', '%' . $q . '%')
                  ->orWhere('sc.razon_social', 'like', '%' . $q . '%')
                  ->orWhere('sc.id', 'like', '%' . $q . '%')
                  ->orWhere('sc.cuenta_id', 'like', '%' . $q . '%')
                  ->orWhere('sc.account_id', 'like', '%' . $q . '%')
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.external_rfc')) like ?", ['%' . $q . '%'])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.created_by')) like ?", ['%' . $q . '%'])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.created_by_name')) like ?", ['%' . $q . '%'])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.created_by_email')) like ?", ['%' . $q . '%']);
            });
        }

        if (in_array($status, ['validado', 'pendiente', 'bloqueado'], true)) {
            if ($status === 'validado') {
                $base->whereNotNull('sc.validated_at');
            } elseif ($status === 'pendiente') {
                $base->whereNull('sc.validated_at');
            } else {
                $base->where(function ($w) {
                    $w->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.blocked')) IN ('1','true','TRUE')")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.status')) IN ('blocked','error','invalid')");
                });
            }
        }

        if (in_array($origin, ['cliente', 'externo'], true)) {
            if ($origin === 'externo') {
                $base->where(function ($w) {
                    $w->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.from_external')) IN ('1','true','TRUE')")
                      ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.is_external')) IN ('1','true','TRUE')")
                      ->orWhereRaw("NULLIF(JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.external_rfc')), '') IS NOT NULL");
                });
            } else {
                $base->where(function ($w) {
                    $w->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.from_external')),'0') NOT IN ('1','true','TRUE')")
                      ->whereRaw("COALESCE(JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.is_external')),'0') NOT IN ('1','true','TRUE')")
                      ->whereRaw("NULLIF(JSON_UNQUOTE(JSON_EXTRACT(sc.meta,'$.external_rfc')), '') IS NULL");
                });
            }
        }

        // =========================================================
        // Orden + paginación
        // =========================================================
        $base->orderByDesc('sc.updated_at')->orderByDesc('sc.created_at');

        $total = (clone $base)->count();
        $items = $base->forPage($page, $per)->get();

        // =========================================================
        // Enriquecer: nombre de cuenta (ADMIN + CLIENTES)
        // =========================================================
        $items = $this->hydrateAccountNames($items);

        $rows = new LengthAwarePaginator(
            $items,
            $total,
            $per,
            $page,
            [
                'path'  => $request->url(),
                'query' => $request->query(),
            ]
        );

        return view('admin.sat.ops.credentials.index', [
            'title'  => 'SAT · Operación · Credenciales',
            'rows'   => $rows,
            'q'      => $q,
            'status' => $status,
            'origin' => $origin,
            'per'    => $per,
        ]);
    }

    // =========================================================
    // Descargas CER/KEY (OPS) — robusto + filename RFC.ext
    // =========================================================
    public function cer(string $id): Response|StreamedResponse
    {
        return $this->downloadFile($id, 'cer');
    }

    public function key(string $id): Response|StreamedResponse
    {
        return $this->downloadFile($id, 'key');
    }

    private function downloadFile(string $id, string $kind): Response|StreamedResponse
{
    $row = DB::connection('mysql_clientes')
        ->table('sat_credentials')
        ->select(['id', 'rfc', 'cer_path', 'key_path'])
        ->where('id', $id)
        ->first();

    if (!$row) {
        abort(404, 'Credencial no encontrada.');
    }

    $rfc = strtoupper(trim((string) ($row->rfc ?? 'CSD')));
    $ext = $kind === 'cer' ? 'cer' : 'key';
    $downloadName = ($rfc !== '' ? $rfc : 'CSD') . '.' . $ext;

    $pathRaw = $kind === 'cer'
        ? (string) ($row->cer_path ?? '')
        : (string) ($row->key_path ?? '');

    $pathRaw = trim((string) $pathRaw);

    if ($pathRaw === '') {
        abort(404, strtoupper($kind) . ' no disponible para esta credencial.');
    }

    // ---------------------------------------------------------
    // Normalización y candidatos de búsqueda (NO destructivo)
    // ---------------------------------------------------------
    $orig = str_replace('\\', '/', $pathRaw);
    $orig = preg_replace('#/+#', '/', $orig);
    $orig = trim($orig);

    // Si viene como URL, stream directo (caso raro, pero soportado)
    if (preg_match('#^https?://#i', $orig)) {
        return response()->streamDownload(function () use ($orig) {
            $ctx = stream_context_create(['http' => ['timeout' => 30]]);
            $h = @fopen($orig, 'rb', false, $ctx);
            if ($h) {
                while (!feof($h)) {
                    echo fread($h, 8192);
                }
                fclose($h);
            }
        }, $downloadName);
    }

    // Absoluta Windows/Linux
    try {
        if ((Str::startsWith($orig, ['C:/', 'D:/', 'E:/', '/'])) && is_file($orig)) {
            return response()->download($orig, $downloadName);
        }
    } catch (\Throwable) {
        // ignore
    }

    // Construye candidatos: intentamos varias formas SIN romper "sat/..."
    $candidates = [];
    $push = function (string $p) use (&$candidates) {
        $p = str_replace('\\', '/', $p);
        $p = preg_replace('#/+#', '/', $p);
        $p = preg_replace('#^/+#', '', $p);
        $p = trim($p);
        if ($p !== '' && !in_array($p, $candidates, true)) {
            $candidates[] = $p;
        }
    };

    // Original tal cual (muy importante para tus casos: sat/certs/... y sat/keys/...)
    $push($orig);

    // Variantes comunes que a veces quedan guardadas en BD
    $tmp = $orig;
    foreach ([
        '#^storage/app/public/#',
        '#^storage/app/#',
        '#^app/public/#',
        '#^app/#',
        '#^public/#',
        '#^storage/#',
    ] as $rx) {
        $tmp2 = preg_replace($rx, '', $tmp);
        if (is_string($tmp2)) $push($tmp2);
    }

    // También probamos con prefijo "public/" (a veces se guardan así)
    foreach ($candidates as $p) {
        $push('public/' . $p);
    }

    // ---------------------------------------------------------
    // 1) Probar discos (preferimos public/local)
    // ---------------------------------------------------------
    $diskOrder = ['public', 'local'];

    // Añade discos definidos extra (por si tienen uno específico)
    try {
        $defined = array_keys((array) config('filesystems.disks', []));
        foreach ($defined as $d) {
            if (!in_array($d, $diskOrder, true)) $diskOrder[] = $d;
        }
    } catch (\Throwable) {
        // ignore
    }

    foreach ($diskOrder as $disk) {
        try {
            $d = Storage::disk($disk);
        } catch (\Throwable) {
            continue;
        }

        foreach ($candidates as $p) {
            try {
                if ($d->exists($p)) {
                    // download() es lo mejor; si no existe en algún driver, fallback a path()
                    try {
                        return $d->download($p, $downloadName);
                    } catch (\Throwable) {
                        try {
                            $abs = method_exists($d, 'path') ? $d->path($p) : null;
                            if ($abs && is_file($abs)) {
                                return response()->download($abs, $downloadName);
                            }
                        } catch (\Throwable) {
                            // sigue intentando
                        }
                    }
                }
            } catch (\Throwable) {
                // sigue intentando
            }
        }
    }

    // ---------------------------------------------------------
    // 2) Probar rutas absolutas dentro de storage (muy común)
    // ---------------------------------------------------------
    foreach ($candidates as $p) {
        $abs1 = storage_path('app/' . $p);
        if (is_file($abs1)) {
            return response()->download($abs1, $downloadName);
        }

        $abs2 = storage_path('app/public/' . $p);
        if (is_file($abs2)) {
            return response()->download($abs2, $downloadName);
        }
    }

    // ---------------------------------------------------------
    // Log de diagnóstico (solo server-side)
    // ---------------------------------------------------------
    try {
        \Log::warning('SAT OPS downloadFile: archivo no encontrado', [
            'id'         => $id,
            'kind'       => $kind,
            'path_raw'   => $pathRaw,
            'candidates' => $candidates,
            'disks'      => $diskOrder,
        ]);
    } catch (\Throwable) {
        // ignore
    }

    abort(404, 'Archivo no encontrado en almacenamiento: ' . $pathRaw);
}

// =========================================================
// DELETE (OPS) — usado por sat-ops-credentials.js (fetch DELETE)
// =========================================================
public function destroy(Request $request, string $id): JsonResponse
{
    try {
        $conn = DB::connection('mysql_clientes');

        $row = $conn->table('sat_credentials')
            ->select(['id', 'rfc', 'cer_path', 'key_path'])
            ->where('id', $id)
            ->first();

        if (!$row) {
            return response()->json(['message' => 'Credencial no encontrada.'], 404);
        }

        $rfc = strtoupper(trim((string) ($row->rfc ?? '')));
        $cer = trim((string) ($row->cer_path ?? ''));
        $key = trim((string) ($row->key_path ?? ''));

        // 1) Borra registro
        $conn->table('sat_credentials')->where('id', $id)->delete();

        // 2) Best-effort: borra archivos si existen (NO rompe si falla)
        $this->tryDeleteStoredFile($cer);
        $this->tryDeleteStoredFile($key);

        return response()->json([
            'ok'      => true,
            'message' => 'Credencial eliminada.',
            'id'      => (string) $id,
            'rfc'     => $rfc,
        ], 200);
    } catch (\Throwable $e) {
        try { Log::error('SAT OPS destroy failed', ['id' => $id, 'e' => $e->getMessage()]); } catch (\Throwable) {}
        return response()->json(['message' => 'No se pudo eliminar la credencial.'], 500);
    }
}

/**
 * Best-effort: intenta borrar un archivo guardado en BD (paths tipo sat/certs/... o public/sat/...).
 * No lanza excepción si no existe / falla.
 */
private function tryDeleteStoredFile(string $pathRaw): void
{
    $pathRaw = trim($pathRaw);
    if ($pathRaw === '') return;

    $p = str_replace('\\', '/', $pathRaw);
    $p = preg_replace('#/+#', '/', $p);
    $p = ltrim($p, '/');
    $p = trim($p);
    if ($p === '') return;

    $cands = [];
    $push = function (string $x) use (&$cands) {
        $x = str_replace('\\', '/', $x);
        $x = preg_replace('#/+#', '/', $x);
        $x = ltrim($x, '/');
        $x = trim($x);
        if ($x !== '' && !in_array($x, $cands, true)) $cands[] = $x;
    };

    // original y normalizaciones típicas
    $push($p);
    $push(preg_replace('#^public/#', '', $p) ?? $p);
    $push(preg_replace('#^storage/app/public/#', '', $p) ?? $p);
    $push('public/' . $p);

    // discos a intentar
    $disks = ['public', 'local'];
    try {
        foreach (array_keys((array) config('filesystems.disks', [])) as $d) {
            if (!in_array($d, $disks, true)) $disks[] = $d;
        }
    } catch (\Throwable) {}

    foreach ($disks as $disk) {
        try { $d = Storage::disk($disk); } catch (\Throwable) { continue; }

        foreach ($cands as $cand) {
            try {
                if ($d->exists($cand)) {
                    $d->delete($cand);
                    return;
                }
            } catch (\Throwable) {}
        }
    }

    // fallback a rutas absolutas dentro de storage
    foreach ($cands as $cand) {
        try {
            $abs1 = storage_path('app/' . $cand);
            if (is_file($abs1)) { @unlink($abs1); return; }

            $abs2 = storage_path('app/public/' . $cand);
            if (is_file($abs2)) { @unlink($abs2); return; }
        } catch (\Throwable) {}
    }
}



    // =========================================================
    // Resolver nombre de cuenta: primero ADMIN.accounts, luego CLIENTES.*
    // =========================================================
    private function hydrateAccountNames($items)
    {
        // 1) Intenta admin.accounts (si hubiera match directo)
        $items = $this->hydrateAccountNamesFromAdmin($items);

        // 2) ✅ Nuevo: fallback real para UUID -> billing_statements.snapshot.account
        $items = $this->hydrateAccountNamesFromAdminBillingSnapshot($items);

        // 3) Fallbacks legacy: mysql_clientes (companies/accounts/clientes)
        $items = $this->hydrateAccountNamesFromClientes($items);

        return $items;
    }


    private function hydrateAccountNamesFromAdmin($items)
    {
        try {
            $ids = collect($items)
                ->map(fn($r) => trim((string) ($r->account_ref_id ?? '')))
                ->filter(fn($v) => $v !== '' && $v !== '0' && $v !== '—')
                ->unique()
                ->values();

            if ($ids->isEmpty()) return $items;

            $accConn = $this->adminAccountsConnection();
            if (!$accConn) return $items;

            $schema = Schema::connection($accConn);
            if (!$schema->hasTable('accounts')) return $items;

            // ===========================
            // Detectar columnas disponibles
            // ===========================
            $pickCol = function (array $cands) use ($schema): ?string {
                foreach ($cands as $c) {
                    try {
                        if ($schema->hasColumn('accounts', $c)) return $c;
                    } catch (\Throwable) {}
                }
                return null;
            };

            $nameCol   = $pickCol(['razon_social','nombre','name','title']);
            $emailCol  = $pickCol(['email','correo','email_contacto','correo_contacto']);
            $phoneCol  = $pickCol(['telefono','phone','tel','celular','movil']);
            $statusCol = $pickCol(['estado_cuenta','status','estado','is_blocked','blocked']);
            $planCol   = $pickCol(['plan','plan_name','license','license_name','paquete','package']);
            $createdCol= $pickCol(['created_at','alta','created']);

            // llave (columna para whereIn)
            $keyCandidates = ['id', 'uuid', 'account_uuid', 'public_id', 'uid', 'external_id', 'account_id', 'key'];
            $existing = [];
            foreach ($keyCandidates as $c) {
                try { if ($schema->hasColumn('accounts', $c)) $existing[] = $c; } catch (\Throwable) {}
            }
            if (empty($existing)) return $items;

            $sample = (string) $ids->first();
            $looksUuid = (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $sample);

            $order = $looksUuid
                ? ['uuid','account_uuid','public_id','uid','external_id','account_id','id','key']
                : ['id','account_id','public_id','uuid','account_uuid','uid','external_id','key'];

            $keyCol = null;
            foreach ($order as $cand) {
                if (in_array($cand, $existing, true)) { $keyCol = $cand; break; }
            }
            if (!$keyCol) $keyCol = $existing[0];

            // ===========================
            // SELECT dinámico
            // ===========================
            $sel = [
                $keyCol . ' as k',
                ($nameCol ? ($nameCol . ' as n') : DB::raw("NULL as n")),
                ($emailCol ? ($emailCol . ' as e') : DB::raw("NULL as e")),
                ($phoneCol ? ($phoneCol . ' as p') : DB::raw("NULL as p")),
                ($statusCol ? ($statusCol . ' as s') : DB::raw("NULL as s")),
                ($planCol ? ($planCol . ' as pl') : DB::raw("NULL as pl")),
                ($createdCol ? ($createdCol . ' as c') : DB::raw("NULL as c")),
            ];

            $rows = DB::connection($accConn)
                ->table('accounts')
                ->select($sel)
                ->whereIn($keyCol, $ids->all())
                ->get();

            $map = [];
            foreach ($rows as $r) {
                $k  = trim((string) ($r->k ?? ''));
                if ($k === '') continue;

                $map[$k] = [
                    'name'   => trim((string) ($r->n ?? '')),
                    'email'  => trim((string) ($r->e ?? '')),
                    'phone'  => trim((string) ($r->p ?? '')),
                    'status' => trim((string) ($r->s ?? '')),
                    'plan'   => trim((string) ($r->pl ?? '')),
                    'created'=> trim((string) ($r->c ?? '')),
                ];
            }

            foreach ($items as $it) {
                $k = trim((string) ($it->account_ref_id ?? ''));
                if ($k === '' || !isset($map[$k])) continue;

                $payload = $map[$k];

                if (empty($it->account_name) && ($payload['name'] ?? '') !== '') {
                    $it->account_name = $payload['name'];
                }

                // Siempre marcamos hint si se hidrató algo desde admin
                if (empty($it->account_hint)) {
                    $it->account_hint = 'Admin · accounts';
                }

                if (empty($it->account_email) && ($payload['email'] ?? '') !== '') {
                    $it->account_email = $payload['email'];
                }
                if (empty($it->account_phone) && ($payload['phone'] ?? '') !== '') {
                    $it->account_phone = $payload['phone'];
                }
                if (empty($it->account_status) && ($payload['status'] ?? '') !== '') {
                    $it->account_status = $payload['status'];
                }
                if (empty($it->account_plan) && ($payload['plan'] ?? '') !== '') {
                    $it->account_plan = $payload['plan'];
                }
                if (empty($it->account_created_at) && ($payload['created'] ?? '') !== '') {
                    $it->account_created_at = $payload['created'];
                }
            }
        } catch (\Throwable) {
            // no romper vista
        }

        return $items;
    }

    private function hydrateAccountNamesFromAdminBillingSnapshot($items)
    {
        try {
            // =========================================================
            // Objetivo:
            // - Cuando sat_credentials.account_id es UUID y NO existe en admin.accounts,
            //   tomamos la info de cuenta desde mysql_admin.billing_statements.snapshot
            //   snapshot.account.{razon_social,email,rfc,is_blocked,...}
            //   snapshot.license.{price_key,cycle,is_pro,...}
            // =========================================================

            $ids = collect($items)
                ->map(fn($r) => trim((string)($r->account_ref_id ?? '')))
                ->filter(fn($v) => $v !== '' && $v !== '0' && $v !== '—')
                ->unique()
                ->values();

            if ($ids->isEmpty()) return $items;

            // Solo UUIDs (porque billing_statements.account_id es varchar(36))
            $isUuid = fn(string $v) => (bool)preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v);

            $uuidIds = $ids->filter(fn($v) => $isUuid((string)$v))->values();
            if ($uuidIds->isEmpty()) return $items;

            // Traer el statement más reciente por account_id (simple: order desc y en PHP nos quedamos con el primero)
            $rows = DB::connection('mysql_admin')
                ->table('billing_statements')
                ->select(['account_id', 'snapshot', 'updated_at', 'created_at'])
                ->whereIn('account_id', $uuidIds->all())
                ->orderByDesc('updated_at')
                ->orderByDesc('created_at')
                ->get();

            if ($rows->isEmpty()) return $items;

            $map = [];
            foreach ($rows as $r) {
                $k = trim((string)($r->account_id ?? ''));
                if ($k === '' || isset($map[$k])) continue; // ya tenemos el más reciente

                $snapRaw = (string)($r->snapshot ?? '');
                if ($snapRaw === '') continue;

                $snap = json_decode($snapRaw, true);
                if (!is_array($snap)) continue;

                $acc = $snap['account'] ?? [];
                $lic = $snap['license'] ?? [];

                $name   = trim((string)($acc['razon_social'] ?? $acc['name'] ?? $acc['nombre'] ?? ''));
                $email  = trim((string)($acc['email'] ?? ''));
                $rfc    = trim((string)($acc['rfc'] ?? ''));
                $blocked= (int)($acc['is_blocked'] ?? 0);

                // status compacto
                $status = $blocked ? 'bloqueada' : 'operando';

                // plan/cycle desde snapshot.license
                $priceKey = trim((string)($lic['price_key'] ?? $lic['plan'] ?? ''));
                $cycle    = trim((string)($lic['cycle'] ?? ''));
                $isPro    = (bool)($lic['is_pro'] ?? false);

                $plan = $priceKey !== '' ? $priceKey : ($isPro ? 'pro' : '');
                if ($cycle !== '' && $plan !== '') $plan = $plan.' · '.$cycle;

                $map[$k] = [
                    'name'   => $name,
                    'email'  => $email,
                    'rfc'    => $rfc,
                    'status' => $status,
                    'plan'   => $plan,
                ];
            }

            if (empty($map)) return $items;

            foreach ($items as $it) {
                $k = trim((string)($it->account_ref_id ?? ''));
                if ($k === '' || !isset($map[$k])) continue;

                $p = $map[$k];

                // solo llena si venía vacío (para respetar admin.accounts si existiera)
                if (empty($it->account_name) && ($p['name'] ?? '') !== '') {
                    $it->account_name = $p['name'];
                }
                if (empty($it->account_hint)) {
                    $it->account_hint = 'Admin · billing_statements.snapshot';
                }
                if (empty($it->account_email) && ($p['email'] ?? '') !== '') {
                    $it->account_email = $p['email'];
                }
                // account_phone no viene en snapshot actual
                if (empty($it->account_status) && ($p['status'] ?? '') !== '') {
                    $it->account_status = $p['status'];
                }
                if (empty($it->account_plan) && ($p['plan'] ?? '') !== '') {
                    $it->account_plan = $p['plan'];
                }

                // Extra útil: si en snapshot viene RFC, lo guardamos como "account_ref" (sin romper nada)
                // (esto ayuda a mostrar algo aunque la cuenta no tenga rfc en admin.accounts)
                if (empty($it->account_ref) && ($p['rfc'] ?? '') !== '') {
                    $it->account_ref = $p['rfc'];
                }
            }

        } catch (\Throwable) {
            // no romper vista
        }

        return $items;
    }


    private function hydrateAccountNamesFromClientes($items)
{
    try {
        $schema = Schema::connection('mysql_clientes');

        $cuentaIds = collect($items)
            ->map(fn($r) => trim((string) ($r->cuenta_id ?? '')))
            ->filter(fn($v) => $v !== '' && $v !== '0' && $v !== '—')
            ->unique()
            ->values();

        $refIds = collect($items)
            ->map(fn($r) => trim((string) ($r->account_ref_id ?? '')))
            ->filter(fn($v) => $v !== '' && $v !== '0' && $v !== '—')
            ->unique()
            ->values();

        // ================================
        // 0) Helper: detect UUID
        // ================================
        $looksUuid = function (?string $v): bool {
            $v = trim((string)$v);
            if ($v === '') return false;
            return (bool) preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $v);
        };

        // =========================================================
        // 1) ✅ PRIORIDAD: companies (account_id -> razon_social)
        //    Esto hace que la cuenta se vea "real" para UUID cuenta_id
        // =========================================================
        if ($schema->hasTable('companies')) {

            $hasAccountId = $schema->hasColumn('companies', 'account_id');
            $hasRazon     = $schema->hasColumn('companies', 'razon_social');

            if ($hasAccountId && $hasRazon) {

                // a) por cuenta_id
                if ($cuentaIds->isNotEmpty()) {
                    $rows = DB::connection('mysql_clientes')
                        ->table('companies')
                        ->select(['account_id as k', 'razon_social as n'])
                        ->whereIn('account_id', $cuentaIds->all())
                        ->get();

                    $map = [];
                    foreach ($rows as $r) {
                        $k = trim((string) ($r->k ?? ''));
                        $n = trim((string) ($r->n ?? ''));
                        if ($k !== '' && $n !== '') $map[$k] = $n;
                    }

                    foreach ($items as $it) {
                        if (!empty($it->account_name)) continue;
                        $k = trim((string) ($it->cuenta_id ?? ''));
                        if ($k !== '' && isset($map[$k])) {
                            $it->account_name = $map[$k];
                            $it->account_hint = 'Clientes · companies (account_id)';
                        }
                    }
                }

                // b) fallback por account_ref_id (si viene un UUID ahí)
                if ($refIds->isNotEmpty() && $looksUuid((string)$refIds->first())) {
                    $rows2 = DB::connection('mysql_clientes')
                        ->table('companies')
                        ->select(['account_id as k', 'razon_social as n'])
                        ->whereIn('account_id', $refIds->all())
                        ->get();

                    $map2 = [];
                    foreach ($rows2 as $r) {
                        $k = trim((string) ($r->k ?? ''));
                        $n = trim((string) ($r->n ?? ''));
                        if ($k !== '' && $n !== '') $map2[$k] = $n;
                    }

                    foreach ($items as $it) {
                        if (!empty($it->account_name)) continue;
                        $k = trim((string) ($it->account_ref_id ?? ''));
                        if ($k !== '' && isset($map2[$k])) {
                            $it->account_name = $map2[$k];
                            $it->account_hint = 'Clientes · companies (account_id)';
                        }
                    }
                }
            }

                            // ================================
                // Extra (si existen columnas): email / teléfono
                // ================================
                $emailCol = null;
                foreach (['email','correo','email_contacto','correo_contacto'] as $c) {
                    try { if ($schema->hasColumn('companies', $c)) { $emailCol = $c; break; } } catch (\Throwable) {}
                }
                $phoneCol = null;
                foreach (['telefono','phone','tel','celular','movil'] as $c) {
                    try { if ($schema->hasColumn('companies', $c)) { $phoneCol = $c; break; } } catch (\Throwable) {}
                }

                if (($emailCol || $phoneCol) && $cuentaIds->isNotEmpty()) {
                    $sel = ['account_id as k'];
                    if ($emailCol) $sel[] = $emailCol.' as e';
                    else $sel[] = DB::raw("NULL as e");
                    if ($phoneCol) $sel[] = $phoneCol.' as p';
                    else $sel[] = DB::raw("NULL as p");

                    $rowsX = DB::connection('mysql_clientes')
                        ->table('companies')
                        ->select($sel)
                        ->whereIn('account_id', $cuentaIds->all())
                        ->get();

                    $mapX = [];
                    foreach ($rowsX as $r) {
                        $k = trim((string) ($r->k ?? ''));
                        if ($k === '') continue;
                        $mapX[$k] = [
                            'email' => trim((string) ($r->e ?? '')),
                            'phone' => trim((string) ($r->p ?? '')),
                        ];
                    }

                    foreach ($items as $it) {
                        $k = trim((string) ($it->cuenta_id ?? ''));
                        if ($k === '' || !isset($mapX[$k])) continue;

                        if (empty($it->account_email) && ($mapX[$k]['email'] ?? '') !== '') {
                            $it->account_email = $mapX[$k]['email'];
                        }
                        if (empty($it->account_phone) && ($mapX[$k]['phone'] ?? '') !== '') {
                            $it->account_phone = $mapX[$k]['phone'];
                        }
                    }
                }

        }

        // =========================================================
        // 2) accounts (id -> razon_social)  [normalmente ID numérico]
        // =========================================================
        if ($schema->hasTable('accounts')) {
            $nameCol = $schema->hasColumn('accounts', 'razon_social') ? 'razon_social' : null;

            if ($nameCol) {
                // por cuenta_id (si fuera numérico)
                if ($cuentaIds->isNotEmpty() && !$looksUuid((string)$cuentaIds->first())) {
                    $rows = DB::connection('mysql_clientes')
                        ->table('accounts')
                        ->select(['id as k', $nameCol . ' as n'])
                        ->whereIn('id', $cuentaIds->all())
                        ->get();

                    $map = [];
                    foreach ($rows as $r) {
                        $k = trim((string) ($r->k ?? ''));
                        $n = trim((string) ($r->n ?? ''));
                        if ($k !== '' && $n !== '') $map[$k] = $n;
                    }

                    foreach ($items as $it) {
                        if (!empty($it->account_name)) continue;
                        $k = trim((string) ($it->cuenta_id ?? ''));
                        if ($k !== '' && isset($map[$k])) {
                            $it->account_name = $map[$k];
                            $it->account_hint = 'Clientes · accounts (id)';
                        }
                    }
                }

                // fallback por account_ref_id (si fuera numérico)
                if ($refIds->isNotEmpty() && !$looksUuid((string)$refIds->first())) {
                    $rows2 = DB::connection('mysql_clientes')
                        ->table('accounts')
                        ->select(['id as k', $nameCol . ' as n'])
                        ->whereIn('id', $refIds->all())
                        ->get();

                    $map2 = [];
                    foreach ($rows2 as $r) {
                        $k = trim((string) ($r->k ?? ''));
                        $n = trim((string) ($r->n ?? ''));
                        if ($k !== '' && $n !== '') $map2[$k] = $n;
                    }

                    foreach ($items as $it) {
                        if (!empty($it->account_name)) continue;
                        $k = trim((string) ($it->account_ref_id ?? ''));
                        if ($k !== '' && isset($map2[$k])) {
                            $it->account_name = $map2[$k];
                            $it->account_hint = 'Clientes · accounts (id)';
                        }
                    }
                }
            }
        }

        // =========================================================
        // 3) clientes (id -> razon_social) [por si tu cuenta_id fuera id cliente]
        // =========================================================
        if ($schema->hasTable('clientes')) {
            $nameCol = $schema->hasColumn('clientes', 'razon_social') ? 'razon_social' : null;

            if ($nameCol) {
                // por cuenta_id
                if ($cuentaIds->isNotEmpty()) {
                    $rows = DB::connection('mysql_clientes')
                        ->table('clientes')
                        ->select(['id as k', $nameCol . ' as n'])
                        ->whereIn('id', $cuentaIds->all())
                        ->get();

                    $map = [];
                    foreach ($rows as $r) {
                        $k = trim((string) ($r->k ?? ''));
                        $n = trim((string) ($r->n ?? ''));
                        if ($k !== '' && $n !== '') $map[$k] = $n;
                    }

                    foreach ($items as $it) {
                        if (!empty($it->account_name)) continue;
                        $k = trim((string) ($it->cuenta_id ?? ''));
                        if ($k !== '' && isset($map[$k])) {
                            $it->account_name = $map[$k];
                            $it->account_hint = 'Clientes · clientes (id)';
                        }
                    }
                }

                // por account_ref_id
                if ($refIds->isNotEmpty()) {
                    $rows2 = DB::connection('mysql_clientes')
                        ->table('clientes')
                        ->select(['id as k', $nameCol . ' as n'])
                        ->whereIn('id', $refIds->all())
                        ->get();

                    $map2 = [];
                    foreach ($rows2 as $r) {
                        $k = trim((string) ($r->k ?? ''));
                        $n = trim((string) ($r->n ?? ''));
                        if ($k !== '' && $n !== '') $map2[$k] = $n;
                    }

                    foreach ($items as $it) {
                        if (!empty($it->account_name)) continue;
                        $k = trim((string) ($it->account_ref_id ?? ''));
                        if ($k !== '' && isset($map2[$k])) {
                            $it->account_name = $map2[$k];
                            $it->account_hint = 'Clientes · clientes (id)';
                        }
                    }
                }
            }
        }

    } catch (\Throwable) {
        // no romper vista
    }

    return $items;
}


    private function adminAccountsConnection(): ?string
    {
        foreach (['mysql', 'mysql_admin'] as $c) {
            try {
                if (Schema::connection($c)->hasTable('accounts')) return $c;
            } catch (\Throwable) {
                // ignore
            }
        }
        return null;
    }
}
