<?php

declare(strict_types=1);

namespace App\Http\Controllers\Cliente\Sat;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

class FielExternalController extends Controller
{
    /**
     * Tabla REAL que ya usa tu flujo:
     * - invite() inserta aquí
     * - showUploadForm() consulta aquí
     * - storeUpload() actualiza aquí
     * - downloadAuth/update/destroy usan aquí
     */
    private string $table = 'external_fiel_uploads';

    /**
     * Conexión preferida para cliente.
     * Si tu tabla existe en mysql (default), hacemos fallback automático.
     */
    private string $preferredConn = 'mysql_clientes';

    // ======================================================
    // Helpers
    // ======================================================

    /**
     * Resolver account_id robusto (P360 usa varias llaves de sesión).
     */
    protected function accountId(Request $request): int
    {
        $candidates = [
            // P360 / cliente
            'cliente.account_id',
            'cliente.cuenta_id',
            'client.account_id',
            'client.cuenta_id',

            // legacy
            'p360.account_id',
            'p360.cuenta_id',
            'p360v1.account_id',
            'p360v1.cuenta_id',

            // genéricas
            'account_id',
            'cuenta_id',
        ];

        foreach ($candidates as $k) {
            $v = session($k);
            if (is_scalar($v) && (int)$v > 0) return (int)$v;
        }

        // Auth guard web (cliente)
        try {
            $u = $request->user() ?: Auth::guard('web')->user();
            if ($u) {
                foreach (['account_id','cuenta_id','accountId','cuentaId'] as $prop) {
                    try {
                        $vv = $u->{$prop} ?? null;
                        if (is_scalar($vv) && (int)$vv > 0) return (int)$vv;
                    } catch (\Throwable) {}
                }
                // relaciones típicas
                try { $vv = $u->account?->id ?? null; if (is_scalar($vv) && (int)$vv > 0) return (int)$vv; } catch (\Throwable) {}
                try { $vv = $u->cuenta?->id ?? null;  if (is_scalar($vv) && (int)$vv > 0) return (int)$vv; } catch (\Throwable) {}
            }
        } catch (\Throwable) {}

        return 0;
    }

    protected function resolveConnForTable(string $table): string
    {
        foreach (['mysql_clientes', 'mysql'] as $conn) {
            try {
                if (Schema::connection($conn)->hasTable($table)) {
                    return $conn;
                }
            } catch (\Throwable) {
                // ignore
            }
        }
        return $this->preferredConn ?: 'mysql_clientes';
    }

    private function wantsJson(Request $request): bool
    {
        $accept = strtolower((string) $request->header('accept', ''));
        $format = strtolower((string) $request->query('format', ''));
        return $format === 'json' || str_contains($accept, 'application/json');
    }

    /**
     * Detecta si una columna existe en la tabla.
     */
    private function hasCol(string $conn, string $table, string $col): bool
    {
        try {
            return Schema::connection($conn)->hasColumn($table, $col);
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * Devuelve el nombre de columna de path real (file_path / zip_path / path).
     */
    private function detectPathColumn(string $conn, string $table): ?string
    {
        foreach (['file_path','zip_path','path'] as $c) {
            if ($this->hasCol($conn, $table, $c)) return $c;
        }
        return null;
    }

    private function detectSizeColumn(string $conn, string $table): ?string
    {
        foreach (['file_size','size','zip_size'] as $c) {
            if ($this->hasCol($conn, $table, $c)) return $c;
        }
        return null;
    }

    private function detectNameColumn(string $conn, string $table): ?string
    {
        foreach (['file_name','zip_name','name'] as $c) {
            if ($this->hasCol($conn, $table, $c)) return $c;
        }
        return null;
    }

    private function detectUploadedAtColumn(string $conn, string $table): ?string
    {
        foreach (['uploaded_at','subido_at','uploadedAt'] as $c) {
            if ($this->hasCol($conn, $table, $c)) return $c;
        }
        return null;
    }

    // ======================================================
    // INVITAR (CLIENTE AUTENTICADO)
    // ======================================================
    public function invite(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email'      => ['required', 'email'],
            'reference'  => ['nullable', 'string', 'max:120'],
        ]);

        $accountId = $this->accountId($request);
        if ($accountId <= 0) {
            return response()->json([
                'ok'  => false,
                'msg' => 'Cuenta no válida.',
            ], 403);
        }

        $conn = $this->resolveConnForTable($this->table);

        $token = Str::random(48);

        DB::connection($conn)->table($this->table)->insert([
            'account_id'    => $accountId,
            'email_externo' => strtolower(trim((string) $data['email'])),
            'reference'     => $data['reference'] ?? null,
            'token'         => $token,
            'status'        => 'invited',
            'created_at'    => now(),
            'updated_at'    => now(),
        ]);

        $signedUrl = URL::temporarySignedRoute(
            'cliente.sat.fiel.external.upload.form',
            now()->addDays(7),
            ['token' => $token]
        );

        // Si falla correo, NO queremos tumbar el flujo
        try {
            Mail::raw(
                "Has sido invitado a subir un archivo ZIP con información de FIEL.\n\n" .
                "Enlace seguro:\n{$signedUrl}\n\n" .
                "Este enlace expira en 7 días.",
                function ($message) use ($data) {
                    $message
                        ->to($data['email'])
                        ->subject('Invitación para carga de FIEL');
                }
            );
        } catch (\Throwable $e) {
            Log::warning('[FIEL-EXTERNAL] invite mail failed', ['err' => $e->getMessage()]);
        }

        return response()->json([
            'ok'  => true,
            'msg' => 'Invitación enviada correctamente.',
            'url' => $signedUrl,
        ]);
    }

    // ======================================================
    // FORMULARIO PÚBLICO (SIGNED)
    // ======================================================
    public function showUploadForm(Request $request)
    {
        $token = (string) $request->query('token', '');
        if ($token === '') abort(403, 'Invitación inválida.');

        $conn = $this->resolveConnForTable($this->table);

        $row = DB::connection($conn)->table($this->table)
            ->where('token', $token)
            ->where('status', 'invited')
            ->first();

        if (!$row) {
            abort(403, 'Invitación inválida o expirada.');
        }

        return view('cliente.sat.fiel.external_upload', [
            'token' => $token,
        ]);
    }

    // ======================================================
    // GUARDAR ZIP (PÚBLICO, SIGNED)
    // ======================================================
    public function storeUpload(Request $request)
    {
        $token = (string) $request->query('token', '');
        if ($token === '') abort(403, 'Invitación inválida.');

        $conn  = $this->resolveConnForTable($this->table);
        $table = $this->table;

        $row = DB::connection($conn)->table($table)
            ->where('token', $token)
            ->where('status', 'invited')
            ->first();

        if (!$row) {
            abort(403, 'Invitación inválida o ya utilizada.');
        }

        $request->validate([
            'zip' => ['required', 'file', 'mimes:zip', 'max:51200'], // 50MB
        ]);

        $file = $request->file('zip');

        $disk = 'private'; // storage/app/private
        $uuid = (string) Str::uuid();

        $orig = $file->getClientOriginalName();
        $orig = $orig ? trim($orig) : 'fiel.zip';
        $safeName = preg_replace('/[^A-Za-z0-9\.\-\_]+/', '_', $orig);
        if (!str_ends_with(strtolower((string)$safeName), '.zip')) $safeName .= '.zip';

        $dir  = "fiel/external/{$uuid}";
        $path = "{$dir}/{$safeName}";

        Storage::disk($disk)->makeDirectory($dir);
        Storage::disk($disk)->putFileAs($dir, $file, $safeName);

        $size = (int) ($file->getSize() ?? 0);
        $mime = $file->getMimeType() ?: 'application/zip';

        // Detectar columna de path real para compatibilidad
        $pathCol = $this->detectPathColumn($conn, $table) ?? 'file_path';
        $sizeCol = $this->detectSizeColumn($conn, $table) ?? 'file_size';
        $nameCol = $this->detectNameColumn($conn, $table) ?? 'file_name';
        $upAtCol = $this->detectUploadedAtColumn($conn, $table) ?? 'uploaded_at';

        $upd = [
            $pathCol     => $path,
            $nameCol     => $safeName,
            $sizeCol     => $size,
            'mime'       => $mime,
            'status'     => 'uploaded',
            'updated_at' => now(),
        ];

        if ($this->hasCol($conn, $table, $upAtCol)) {
            $upd[$upAtCol] = now();
        }

        DB::connection($conn)->table($table)
            ->where('id', $row->id)
            ->update($upd);

        return view('cliente.sat.fiel.external_upload_done');
    }

    // ======================================================
    // LISTAR REGISTROS (CLIENTE AUTH) — PARA TABLA DEL DASHBOARD
    // ✅ Compatible con tabla REAL external_fiel_uploads (con o sin columnas de archivo)
    // ======================================================
    public function list(Request $request): \Illuminate\Http\JsonResponse
{
    try {
        $limit = (int) $request->query('limit', 50);
        if ($limit <= 0) $limit = 50;
        if ($limit > 200) $limit = 200;

        $accountId = $this->accountId($request);
        if ($accountId <= 0) {
            return response()->json([
                'ok'   => false,
                'rows' => [],
                'msg'  => 'Cuenta no encontrada.',
            ], 422);
        }

        $conn = $this->resolveConnForTable($this->table);

        $schema = \Illuminate\Support\Facades\Schema::connection($conn);

        if (!$schema->hasTable($this->table)) {
            return response()->json([
                'ok'   => true,
                'rows' => [],
                'msg'  => "Tabla {$this->table} no existe en {$conn}.",
            ], 200);
        }

        // Columnas reales y opcionales
        $hasCuentaId   = $schema->hasColumn($this->table, 'cuenta_id');   // UUID en tu tabla
        $hasAccountId  = $schema->hasColumn($this->table, 'account_id');  // INT en tu tabla (15)
        $hasCreatedAt  = $schema->hasColumn($this->table, 'created_at');
        $hasUpdatedAt  = $schema->hasColumn($this->table, 'updated_at');

        // Opcionales (si existen en otro ambiente/migración)
        $hasFilePath   = $schema->hasColumn($this->table, 'file_path');
        $hasFileName   = $schema->hasColumn($this->table, 'file_name');
        $hasFileSize   = $schema->hasColumn($this->table, 'file_size');
        $hasStatus     = $schema->hasColumn($this->table, 'status');
        $hasUploadedAt = $schema->hasColumn($this->table, 'uploaded_at');

        // Select mínimo seguro (solo columnas que existan)
        $select = ['id'];

        if ($hasCuentaId)  $select[] = 'cuenta_id';
        if ($hasAccountId) $select[] = 'account_id';

        // Estas SÍ existen en tu screenshot
        if ($schema->hasColumn($this->table, 'rfc'))           $select[] = 'rfc';
        if ($schema->hasColumn($this->table, 'razon_social'))  $select[] = 'razon_social';
        if ($schema->hasColumn($this->table, 'reference'))     $select[] = 'reference';
        if ($schema->hasColumn($this->table, 'email_externo')) $select[] = 'email_externo';
        if ($schema->hasColumn($this->table, 'token'))         $select[] = 'token';

        // Opcionales
        if ($hasFilePath)   $select[] = 'file_path';
        if ($hasFileName)   $select[] = 'file_name';
        if ($hasFileSize)   $select[] = 'file_size';
        if ($hasStatus)     $select[] = 'status';
        if ($hasUploadedAt) $select[] = 'uploaded_at';

        if ($hasCreatedAt)  $select[] = 'created_at';
        if ($hasUpdatedAt)  $select[] = 'updated_at';

        $q = \Illuminate\Support\Facades\DB::connection($conn)
            ->table($this->table)
            ->select($select);

        // ==========================================================
        // ✅ FIX URGENTE:
        // Tu sesión usa INT (15) y tu tabla guarda account_id=15.
        // cuenta_id es UUID, así que NO debemos filtrar por cuenta_id.
        // ==========================================================
        if ($hasAccountId) {
            $q->where('account_id', (int) $accountId);
        } elseif ($hasCuentaId) {
            // Fallback solo si NO existe account_id
            $q->where('cuenta_id', (string) $accountId);
        } else {
            return response()->json([
                'ok'   => false,
                'rows' => [],
                'msg'  => "La tabla {$this->table} no tiene cuenta_id ni account_id para filtrar.",
            ], 500);
        }

        // ✅ NO filtrar por file_path (tu tabla actual no lo tiene)
        if ($hasStatus) {
            $q->orderByRaw("CASE WHEN status='uploaded' THEN 0 WHEN status='invited' THEN 1 ELSE 2 END");
        }

        $q->orderByDesc('id')->limit($limit);

        $rows = $q->get()->map(function ($r) use ($hasFileName, $hasFileSize, $hasStatus, $hasFilePath, $hasUploadedAt) {
            $rfc  = isset($r->rfc) ? (string) $r->rfc : '';
            $name = isset($r->razon_social) ? (string) $r->razon_social : '';
            $ref  = isset($r->reference) ? (string) $r->reference : '';
            $mail = isset($r->email_externo) ? (string) $r->email_externo : '';

            $fileName = ($hasFileName && !empty($r->file_name)) ? (string) $r->file_name : '—';
            $fileSize = ($hasFileSize && isset($r->file_size)) ? (int) $r->file_size : 0;

            // Estado: si hay status úsalo; si no, infiere si hay file_path o uploaded_at
            $status = '—';
            if ($hasStatus && !empty($r->status)) {
                $status = (string) $r->status;
            } else {
                $hasUploadSignal =
                    ($hasFilePath && !empty($r->file_path)) ||
                    ($hasUploadedAt && !empty($r->uploaded_at));
                $status = $hasUploadSignal ? 'uploaded' : 'invited';
            }

            return [
                'id'           => $r->id ?? null,
                'rfc'          => $rfc,
                'razon_social' => $name,
                'reference'    => $ref,
                'email'        => $mail,
                'file_name'    => $fileName,
                'file_size'    => $fileSize,
                'status'       => $status,
                'created_at'   => $r->created_at ?? null,
            ];
        })->values()->all();

        return response()->json([
            'ok'   => true,
            'rows' => $rows,
        ], 200);

    } catch (\Throwable $e) {
        \Illuminate\Support\Facades\Log::error('[FIEL-EXTERNAL] list() error', [
            'err'  => $e->getMessage(),
            'file' => $e->getFile(),
            'line' => $e->getLine(),
        ]);

        return response()->json([
            'ok'   => false,
            'rows' => [],
            'msg'  => 'Error al cargar registros externos.',
        ], 500);
    }
}

    // ======================================================
    // DESCARGAR ZIP (CLIENTE AUTH)
    // ======================================================
    public function downloadAuth(int $id)
    {
        $req = request();

        // ======================================================
        // 0) Resolver cuenta
        // ======================================================
        $accountId = (int) $this->accountId($req);
        if ($accountId <= 0) abort(403);

        $conn  = $this->resolveConnForTable($this->table);
        $table = $this->table;

        $pathCol = $this->detectPathColumn($conn, $table) ?? 'file_path';
        $nameCol = $this->detectNameColumn($conn, $table) ?? 'file_name';

        // ======================================================
        // 1) RFCs permitidos para el account actual (whitelist)
        //    Fuente: sat_credentials (preferido) -> companies (fallback)
        // ======================================================
        $allowedRfcs = [];
        try {
            if (Schema::connection($conn)->hasTable('sat_credentials')
                && Schema::connection($conn)->hasColumn('sat_credentials', 'account_id')
                && Schema::connection($conn)->hasColumn('sat_credentials', 'rfc')
            ) {
                $allowedRfcs = DB::connection($conn)->table('sat_credentials')
                    ->where('account_id', $accountId)
                    ->whereNotNull('rfc')
                    ->pluck('rfc')
                    ->map(fn($x) => strtoupper(trim((string)$x)))
                    ->filter(fn($x) => $x !== '')
                    ->unique()
                    ->values()
                    ->all();
            }

            if (!$allowedRfcs) {
                if (Schema::connection($conn)->hasTable('companies')
                    && Schema::connection($conn)->hasColumn('companies', 'account_id')
                    && Schema::connection($conn)->hasColumn('companies', 'rfc')
                ) {
                    $allowedRfcs = DB::connection($conn)->table('companies')
                        ->where('account_id', $accountId)
                        ->whereNotNull('rfc')
                        ->pluck('rfc')
                        ->map(fn($x) => strtoupper(trim((string)$x)))
                        ->filter(fn($x) => $x !== '')
                        ->unique()
                        ->values()
                        ->all();
                }
            }
        } catch (\Throwable $e) {
            Log::warning('[FIEL-EXTERNAL] downloadAuth allowedRfcs load failed', [
                'account_id' => $accountId,
                'err'        => $e->getMessage(),
            ]);
            $allowedRfcs = [];
        }

        // ======================================================
        // 2) Buscar por (id + account_id). Si no, fallback por RFC permitido
        // ======================================================
        $row = DB::connection($conn)->table($table)
            ->where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$row) {
            // Fallback: buscar solo por id y validar RFC contra whitelist del account actual
            $rowAny = DB::connection($conn)->table($table)
                ->where('id', $id)
                ->first();

            if (!$rowAny) {
                Log::warning('[FIEL-EXTERNAL] downloadAuth row not found', [
                    'id'         => $id,
                    'account_id' => $accountId,
                ]);
                abort(404);
            }

            $rowRfc = '';
            try { $rowRfc = strtoupper(trim((string)($rowAny->rfc ?? ''))); } catch (\Throwable) { $rowRfc = ''; }

            if ($rowRfc !== '' && in_array($rowRfc, $allowedRfcs, true)) {
                Log::warning('[FIEL-EXTERNAL] downloadAuth legacy account_id allowed by RFC', [
                    'id'              => $id,
                    'session_account' => $accountId,
                    'row_account_id'  => (int)($rowAny->account_id ?? 0),
                    'rfc'             => $rowRfc,
                    'allowed_rfcs_n'  => count($allowedRfcs),
                ]);
                $row = $rowAny;
            } else {
                Log::warning('[FIEL-EXTERNAL] downloadAuth denied (account mismatch)', [
                    'id'              => $id,
                    'session_account' => $accountId,
                    'row_account_id'  => (int)($rowAny->account_id ?? 0),
                    'row_rfc'         => $rowRfc,
                    'allowed_rfcs_n'  => count($allowedRfcs),
                ]);
                abort(404);
            }
        }

        // ======================================================
        // 3) Resolver path y validar existencia en disco
        // ======================================================
        $stored = '';
        try { $stored = ltrim((string)($row->{$pathCol} ?? ''), '/'); } catch (\Throwable) { $stored = ''; }

        if ($stored === '') {
            Log::warning('[FIEL-EXTERNAL] downloadAuth missing file path', [
                'id'         => $id,
                'account_id' => $accountId,
                'path_col'   => $pathCol,
            ]);
            abort(404);
        }

        $fileName = '';
        try { $fileName = trim((string)($row->{$nameCol} ?? '')); } catch (\Throwable) { $fileName = ''; }
        if ($fileName === '') $fileName = basename($stored);

        $disksToTry = ['private', 'public'];

        $candidates = array_values(array_unique(array_filter([
            $stored,
            (str_starts_with($stored, 'public/') ? substr($stored, 7) : null),
            (str_starts_with($stored, 'private/') ? substr($stored, 8) : null),
        ])));

        $foundDisk = null;
        $foundPath = null;

        foreach ($disksToTry as $disk) {
            foreach ($candidates as $p) {
                try {
                    if (Storage::disk($disk)->exists($p)) {
                        $foundDisk = $disk;
                        $foundPath = $p;
                        break 2;
                    }
                } catch (\Throwable) {}
            }
        }

        if (!$foundDisk || !$foundPath) {
            Log::warning('[FIEL-EXTERNAL] downloadAuth file not found', [
                'id'          => $id,
                'account_id'  => $accountId,
                'stored'      => $stored,
                'candidates'  => $candidates,
                'tried_disks' => $disksToTry,
            ]);
            abort(404, 'Archivo no encontrado.');
        }

        // ======================================================
        // 4) Descargar stream
        // ======================================================
        try {
            $stream = Storage::disk($foundDisk)->readStream($foundPath);
            if (!$stream) abort(404, 'No se pudo abrir el archivo.');

            return response()->streamDownload(function () use ($stream) {
                fpassthru($stream);
                if (is_resource($stream)) fclose($stream);
            }, $fileName, [
                'Content-Type'           => 'application/zip',
                'X-Content-Type-Options' => 'nosniff',
            ]);
        } catch (\Throwable $e) {
            Log::error('[FIEL-EXTERNAL] downloadAuth failed', [
                'id'         => $id,
                'account_id' => $accountId,
                'disk'       => $foundDisk,
                'path'       => $foundPath,
                'err'        => $e->getMessage(),
            ]);
            abort(500, 'No se pudo descargar el archivo.');
        }
    }



    // ======================================================
    // EDITAR METADATA (CLIENTE AUTH)
    // ======================================================
    public function update(Request $request, int $id)
    {
        $accountId = $this->accountId($request);
        if ($accountId <= 0) abort(403);

        $conn = $this->resolveConnForTable($this->table);

        $row = DB::connection($conn)->table($this->table)
            ->where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$row) abort(404);

        $data = $request->validate([
            'rfc'           => ['required', 'string', 'max:13'],
            'razon_social'  => ['nullable', 'string', 'max:190'],
            'fiel_password' => ['nullable', 'string', 'max:190'],
        ]);

        $rfc = strtoupper(trim((string) $data['rfc']));
        $raz = trim((string) ($data['razon_social'] ?? ''));

        $upd = [
            'rfc'          => $rfc,
            'razon_social' => $raz !== '' ? $raz : null,
            'updated_at'   => now(),
        ];

        if (
            array_key_exists('fiel_password', $data) &&
            $data['fiel_password'] !== null &&
            trim((string)$data['fiel_password']) !== ''
        ) {
            // Guardar cifrado si existe columna
            if ($this->hasCol($conn, $this->table, 'fiel_password')) {
                $upd['fiel_password'] = Crypt::encryptString(trim((string)$data['fiel_password']));
            }
        }

        DB::connection($conn)->table($this->table)
            ->where('id', $id)
            ->where('account_id', $accountId)
            ->update($upd);

        if ($this->wantsJson($request)) {
            return response()->json(['ok' => true, 'msg' => 'Actualizado.'], 200);
        }

        return redirect()->back()->with('ok', 'Actualizado.');
    }

    // ======================================================
    // ELIMINAR (CLIENTE AUTH)
    // ======================================================
    public function destroy(Request $request, int $id): JsonResponse
    {
        $accountId = $this->accountId($request);
        if ($accountId <= 0) abort(403);

        $conn = $this->resolveConnForTable($this->table);

        $pathCol = $this->detectPathColumn($conn, $this->table) ?? 'file_path';

        $row = DB::connection($conn)->table($this->table)
            ->where('id', $id)
            ->where('account_id', $accountId)
            ->first();

        if (!$row) abort(404);

        $path = '';
        try { $path = (string)($row->{$pathCol} ?? ''); } catch (\Throwable) { $path = ''; }

        if ($path !== '') {
            try { Storage::disk('private')->delete($path); } catch (\Throwable) {}
            try { Storage::disk('public')->delete($path); } catch (\Throwable) {}
        }

        DB::connection($conn)->table($this->table)
            ->where('id', $id)
            ->where('account_id', $accountId)
            ->delete();

        return response()->json([
            'ok'  => true,
            'msg' => 'Eliminado.',
        ], 200);
    }

    // ======================================================
    // DESCARGA (ADMIN / INTERNO) — SE MANTIENE
    // ======================================================
    public function download(int $id)
    {
        $conn = $this->resolveConnForTable($this->table);

        $pathCol = $this->detectPathColumn($conn, $this->table) ?? 'file_path';
        $nameCol = $this->detectNameColumn($conn, $this->table) ?? 'file_name';

        $row = DB::connection($conn)->table($this->table)->where('id', $id)->first();

        if (!$row) abort(404);

        $path = '';
        try { $path = (string)($row->{$pathCol} ?? ''); } catch (\Throwable) { $path = ''; }
        if ($path === '') abort(404);

        $fileName = '';
        try { $fileName = (string)($row->{$nameCol} ?? ''); } catch (\Throwable) { $fileName = ''; }
        if ($fileName === '') $fileName = basename($path);

        // Intentar private primero, luego public
        if (Storage::disk('private')->exists($path)) {
            return Storage::disk('private')->download($path, $fileName);
        }
        if (Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->download($path, $fileName);
        }

        abort(404, 'Archivo no encontrado en discos.');
    }
}
