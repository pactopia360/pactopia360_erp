<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatRfcService.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class SatRfcService
{
    public function buildRfcOptionsForManual($credList): array
    {
        $out = [];
        $seen = [];

        foreach (collect($credList ?? []) as $c) {
            $rfc = strtoupper(trim((string) data_get($c, 'rfc', '')));
            if ($rfc === '') continue;

            if (isset($seen[$rfc])) continue;

            $estatusRaw = strtolower(trim((string) data_get($c, 'estatus', data_get($c, 'sat_status', data_get($c, 'estatus_sat', '')))));

            $hasValidated =
                !empty(data_get($c, 'validado'))
                || !empty(data_get($c, 'validated_at'))
                || !empty(data_get($c, 'validado_at'))
                || !empty(data_get($c, 'verificado_at'))
                || !empty(data_get($c, 'sat_validated_at'));

            $hasFiles =
                !empty(data_get($c, 'has_files'))
                || !empty(data_get($c, 'has_csd'))
                || !empty(data_get($c, 'cer_path'))
                || !empty(data_get($c, 'key_path'))
                || !empty(data_get($c, 'cer_file'))
                || !empty(data_get($c, 'key_file'));

            $statusOk = in_array($estatusRaw, ['ok','valido','válido','validado','valid','activo','active','enabled'], true);

            $isValid = ($hasValidated || $hasFiles || $statusOk);
            if (!$isValid) continue;

            $alias = trim((string) (
                data_get($c, 'razon_social')
                ?? data_get($c, 'razonSocial')
                ?? data_get($c, 'nombre')
                ?? data_get($c, 'alias')
                ?? data_get($c, 'business_name')
                ?? ''
            ));

            $out[] = ['rf' => $rfc, 'alias' => ($alias !== '' ? $alias : null)];
            $seen[$rfc] = true;
        }

        usort($out, static fn ($a, $b) => strcmp((string) ($a['rf'] ?? ''), (string) ($b['rf'] ?? '')));

        return $out;
    }

    public function upsertRfc(string $cuentaId, string $rfc, ?string $alias, Request $request, string $trace): SatCredential
    {
        $cuentaId = trim($cuentaId);
        $rfc = strtoupper(trim($rfc));
        $rfc = preg_replace('/\s+/', '', $rfc) ?: $rfc;

        $conn = 'mysql_clientes';

        $cred = SatCredential::on($conn)
            ->where('cuenta_id', $cuentaId)
            ->whereRaw('UPPER(rfc) = ?', [$rfc])
            ->first();

        if (!$cred) {
            $cred = new SatCredential();
            $cred->setConnection($conn);
            $cred->cuenta_id = $cuentaId;
            $cred->rfc       = $rfc;

            try {
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                if ($schema->hasColumn($table, 'estatus') && empty($cred->estatus)) $cred->estatus = 'pending';
                if ($schema->hasColumn($table, 'validado') && $cred->validado === null) $cred->validado = 0;
                if ($schema->hasColumn($table, 'source') && empty($cred->source)) $cred->source = 'cliente';
            } catch (\Throwable) {}
        } else {
            $cred->setConnection($conn);
        }

        if ($alias !== null) {
            $alias = trim((string) $alias);
            if ($alias === '') $alias = null;
        }

        if ($alias !== null) {
            $applied = false;
            try {
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                if ($schema->hasColumn($table, 'razon_social')) { $cred->razon_social = $alias; $applied = true; }
                elseif ($schema->hasColumn($table, 'alias')) { $cred->alias = $alias; $applied = true; }
            } catch (\Throwable) {}

            if (!$applied) $cred->razon_social = $alias;
        }

        try {
            $table  = $cred->getTable();
            $schema = Schema::connection($conn);

            if ($schema->hasColumn($table, 'meta')) {
                $meta = [];
                $cur  = $cred->meta ?? null;

                if (is_array($cur)) $meta = $cur;
                elseif (is_string($cur) && $cur !== '') {
                    $tmp = json_decode($cur, true);
                    if (is_array($tmp)) $meta = $tmp;
                }

                $meta['last_register_at'] = now()->toDateTimeString();
                $meta['last_register_ip'] = $request->ip();
                $meta['last_register_ua'] = (string) $request->userAgent();
                $meta['trace_id']         = $trace;

                $cred->meta = $meta;
            }
        } catch (\Throwable) {}

        $cred->save();

        return $cred;
    }

    public function saveAlias(string $cuentaId, string $rfc, ?string $alias, Request $request, string $trace): SatCredential
    {
        $cuentaId = trim($cuentaId);
        $rfc = strtoupper(trim($rfc));
        $rfc = preg_replace('/\s+/', '', $rfc) ?: $rfc;

        if ($alias !== null) {
            $alias = trim((string) $alias);
            if ($alias === '') $alias = null;
        }

        $conn = 'mysql_clientes';

        $cred = SatCredential::on($conn)
            ->where('cuenta_id', $cuentaId)
            ->whereRaw('UPPER(rfc) = ?', [$rfc])
            ->first();

        if (!$cred) {
            $cred = new SatCredential();
            $cred->setConnection($conn);
            $cred->cuenta_id = $cuentaId;
            $cred->rfc       = $rfc;

            try {
                $table  = $cred->getTable();
                $schema = Schema::connection($conn);

                if ($schema->hasColumn($table, 'estatus') && empty($cred->estatus)) $cred->estatus = 'pending';
                if ($schema->hasColumn($table, 'validado') && $cred->validado === null) $cred->validado = 0;
                if ($schema->hasColumn($table, 'source') && empty($cred->source)) $cred->source = 'cliente';
            } catch (\Throwable) {}
        } else {
            $cred->setConnection($conn);
        }

        $applied = false;
        try {
            $table  = $cred->getTable();
            $schema = Schema::connection($conn);

            if ($schema->hasColumn($table, 'razon_social')) { $cred->razon_social = $alias; $applied = true; }
            elseif ($schema->hasColumn($table, 'alias')) { $cred->alias = $alias; $applied = true; }
        } catch (\Throwable) {}

        if (!$applied) $cred->razon_social = $alias;

        try {
            $table  = $cred->getTable();
            $schema = Schema::connection($conn);

            if ($schema->hasColumn($table, 'meta')) {
                $meta = [];
                $cur  = $cred->meta ?? null;

                if (is_array($cur)) $meta = $cur;
                elseif (is_string($cur) && $cur !== '') {
                    $tmp = json_decode($cur, true);
                    if (is_array($tmp)) $meta = $tmp;
                }

                $meta['alias_updated_at'] = now()->toDateTimeString();
                $meta['alias_updated_ip'] = $request->ip();
                $meta['alias_updated_ua'] = (string) $request->userAgent();
                $meta['trace_id']         = $trace;

                $cred->meta = $meta;
            }
        } catch (\Throwable) {}

        $cred->save();

        return $cred;
    }

    public function deleteRfc(string $cuentaId, string $rfcUpper): array
    {
        $cuentaId = trim($cuentaId);
        $rfcUpper = strtoupper(trim($rfcUpper));
        $rfcUpper = preg_replace('/\s+/', '', $rfcUpper) ?: $rfcUpper;

        $conn = 'mysql_clientes';

        $cred = SatCredential::on($conn)
            ->where('cuenta_id', $cuentaId)
            ->whereRaw('UPPER(rfc) = ?', [$rfcUpper])
            ->first();

        if (!$cred) {
            return ['ok' => false, 'status' => 404, 'msg' => 'RFC no encontrado en tu cuenta.'];
        }

        // protección: si tiene descargas asociadas
        try {
            if (Schema::connection($conn)->hasTable('sat_downloads')) {
                $dlHas = DB::connection($conn)->table('sat_downloads')
                    ->where(function ($q) use ($cuentaId) {
                        $q->where('cuenta_id', $cuentaId);
                        try {
                            if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'account_id')) {
                                $q->orWhere('account_id', $cuentaId);
                            }
                        } catch (\Throwable) {}
                    })
                    ->whereRaw('UPPER(COALESCE(rfc,"")) = ?', [$rfcUpper])
                    ->exists();

                if ($dlHas) {
                    return ['ok' => false, 'status' => 409, 'msg' => 'No se puede eliminar: el RFC tiene descargas asociadas.'];
                }
            }
        } catch (\Throwable) {
            // si falla, no bloqueamos por esto
        }

        // eliminar archivos
        $table  = $cred->getTable();
        $schema = Schema::connection($conn);

        $cerPath = $schema->hasColumn($table, 'cer_path') ? (string) ($cred->cer_path ?? '') : '';
        $keyPath = $schema->hasColumn($table, 'key_path') ? (string) ($cred->key_path ?? '') : '';

        $diskCandidates = array_values(array_unique(array_filter([
            config('filesystems.disks.private') ? 'private' : null,
            config('filesystems.disks.sat_private') ? 'sat_private' : null,
            config('filesystems.disks.vault') ? 'vault' : null,
            config('filesystems.disks.sat_credentials') ? 'sat_credentials' : null,
            config('filesystems.disks.sat_files') ? 'sat_files' : null,
            config('filesystems.default', 'local'),
        ])));

        $deleteIfExists = static function (string $path) use ($diskCandidates): void {
            $p = ltrim((string) $path, '/');
            if ($p === '') return;

            foreach ($diskCandidates as $disk) {
                try {
                    $d = Storage::disk($disk);
                    if ($d->exists($p)) { $d->delete($p); return; }
                } catch (\Throwable) {}
            }
        };

        $deleteIfExists($cerPath);
        $deleteIfExists($keyPath);

        $cred->delete();

        return ['ok' => true, 'status' => 200, 'msg' => 'RFC eliminado correctamente.', 'rfc' => $rfcUpper];
    }
}