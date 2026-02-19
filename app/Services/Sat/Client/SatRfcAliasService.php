<?php
// C:\wamp64\www\pactopia360_erp\app\Services\Sat\Client\SatRfcAliasService.php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

final class SatRfcAliasService
{
    /**
     * Guarda alias robusto y defensivo.
     *
     * @return array{ok:bool,msg:string,rfc:string,alias:string}
     */
    public function saveAlias(string $cuentaId, string $rfc, ?string $alias, Request $request, string $conn = 'mysql_clientes'): array
    {
        $cuentaId = trim((string) $cuentaId);
        $rfc      = strtoupper(preg_replace('/\s+/', '', trim((string) $rfc)) ?: '');
        $alias    = $alias !== null ? trim((string) $alias) : null;
        if ($alias === '') $alias = null;

        if ($cuentaId === '') {
            return ['ok' => false, 'msg' => 'No se pudo determinar la cuenta actual.', 'rfc' => $rfc, 'alias' => ''];
        }

        if ($rfc === '' || !preg_match('/^[A-ZÑ&]{3,4}\d{6}[A-Z0-9]{3}$/', $rfc)) {
            return ['ok' => false, 'msg' => 'RFC inválido.', 'rfc' => $rfc, 'alias' => ''];
        }

        try {
            $schema = Schema::connection($conn);
            $table  = (new SatCredential())->getTable();

            /** @var SatCredential|null $cred */
            $cred = SatCredential::on($conn)
                ->where('cuenta_id', $cuentaId)
                ->whereRaw('UPPER(rfc) = ?', [$rfc])
                ->first();

            if (!$cred) {
                $cred = new SatCredential();
                $cred->setConnection($conn);
                $cred->cuenta_id = $cuentaId;
                $cred->rfc       = $rfc;

                // Defaults defensivos solo si existen columnas
                try {
                    if ($schema->hasColumn($table, 'estatus') && empty($cred->estatus)) $cred->estatus = 'pending';
                    if ($schema->hasColumn($table, 'validado') && $cred->validado === null) $cred->validado = 0;
                    if ($schema->hasColumn($table, 'source') && empty($cred->source)) $cred->source = 'cliente';
                } catch (\Throwable) {
                    // no-op
                }
            } else {
                $cred->setConnection($conn);
            }

            $appliedToColumn = false;

            try {
                if ($schema->hasColumn($table, 'razon_social')) {
                    $cred->razon_social = $alias;
                    $appliedToColumn = true;
                } elseif ($schema->hasColumn($table, 'alias')) {
                    $cred->alias = $alias;
                    $appliedToColumn = true;
                }
            } catch (\Throwable) {
                $appliedToColumn = false;
            }

            // Meta (si existe) + fallback cuando no hay columna destino
            try {
                if ($schema->hasColumn($table, 'meta')) {
                    $meta = [];
                    $cur = $cred->meta ?? null;

                    if (is_array($cur)) $meta = $cur;
                    elseif (is_object($cur)) $meta = (array) $cur;
                    elseif (is_string($cur) && $cur !== '') {
                        $tmp = json_decode($cur, true);
                        if (is_array($tmp)) $meta = $tmp;
                    }

                    $meta['alias_updated_at'] = now()->toDateTimeString();
                    $meta['alias_updated_ip'] = $request->ip();
                    $meta['alias_updated_ua'] = (string) $request->userAgent();

                    if (!$appliedToColumn) {
                        $meta['alias_fallback'] = $alias;
                        $meta['alias_fallback_reason'] = 'no_alias_column';
                    }

                    $cred->meta = $meta;
                }
            } catch (\Throwable) {
                // no-op
            }

            $cred->save();

            // Resolver alias salida
            $aliasOut = '';
            try { $aliasOut = (string) ($cred->razon_social ?? $cred->alias ?? ''); } catch (\Throwable) { $aliasOut = ''; }

            if ($aliasOut === '') {
                try {
                    $m = $cred->meta ?? null;
                    if (is_string($m) && $m !== '') $m = json_decode($m, true);
                    if (is_array($m) && array_key_exists('alias_fallback', $m)) $aliasOut = (string) ($m['alias_fallback'] ?? '');
                } catch (\Throwable) {}
            }
            if ($aliasOut === '' && $alias !== null) $aliasOut = (string) $alias;

            return ['ok' => true, 'msg' => 'Alias actualizado.', 'rfc' => (string) ($cred->rfc ?? $rfc), 'alias' => $aliasOut];
        } catch (\Throwable $e) {
            Log::error('[SatRfcAliasService:saveAlias] Error', [
                'cuenta_id' => $cuentaId,
                'rfc'       => $rfc,
                'err'       => $e->getMessage(),
            ]);
            return ['ok' => false, 'msg' => 'No se pudo actualizar el alias.', 'rfc' => $rfc, 'alias' => ''];
        }
    }
}
