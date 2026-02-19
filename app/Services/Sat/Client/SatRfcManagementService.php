<?php

declare(strict_types=1);

namespace App\Services\Sat\Client;

use App\Models\Cliente\SatCredential;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

final class SatRfcManagementService
{
    public function __construct(
        private readonly SatClientContext $ctx
    ) {}

    /* ==========================================================
     * REGISTER RFC
     * ========================================================== */

    public function register(Request $request)
    {
        $trace    = $this->ctx->trace();
        $cuentaId = trim($this->ctx->cuentaId());
        $isAjax   = $this->ctx->isAjax($request);

        $data = $request->validate([
            'rfc'   => ['required','string','min:12','max:13'],
            'alias' => ['nullable','string','max:190'],
        ]);

        $rfc   = strtoupper(trim($data['rfc']));
        $alias = isset($data['alias']) ? trim((string)$data['alias']) : null;
        if ($alias === '') $alias = null;

        if ($cuentaId === '') {
            return response()->json(['ok'=>false,'msg'=>'Cuenta inválida','trace_id'=>$trace],403);
        }

        try {
            $conn = 'mysql_clientes';

            $cred = SatCredential::on($conn)
                ->where('cuenta_id',$cuentaId)
                ->whereRaw('UPPER(rfc)=?',[$rfc])
                ->first();

            if (!$cred) {
                $cred = new SatCredential();
                $cred->setConnection($conn);
                $cred->cuenta_id = $cuentaId;
                $cred->rfc       = $rfc;
            }

            if ($alias !== null) {
                if (Schema::connection($conn)->hasColumn($cred->getTable(),'razon_social')) {
                    $cred->razon_social = $alias;
                } elseif (Schema::connection($conn)->hasColumn($cred->getTable(),'alias')) {
                    $cred->alias = $alias;
                }
            }

            $cred->save();

            return response()->json([
                'ok'=>true,
                'rfc'=>$rfc,
                'alias'=>$alias,
                'trace_id'=>$trace
            ]);

        } catch (\Throwable $e) {
            Log::error('[SAT:RFC:register]',['err'=>$e->getMessage()]);
            return response()->json(['ok'=>false,'msg'=>'Error registrando RFC','trace_id'=>$trace],500);
        }
    }

    /* ==========================================================
     * DELETE RFC
     * ========================================================== */

    public function delete(Request $request)
    {
        $trace    = $this->ctx->trace();
        $cuentaId = trim($this->ctx->cuentaId());

        $data = $request->validate([
            'rfc' => ['required','string','min:12','max:13'],
        ]);

        $rfc = strtoupper(trim($data['rfc']));

        try {
            $conn = 'mysql_clientes';

            $cred = SatCredential::on($conn)
                ->where('cuenta_id',$cuentaId)
                ->whereRaw('UPPER(rfc)=?',[$rfc])
                ->first();

            if (!$cred) {
                return response()->json(['ok'=>false,'msg'=>'RFC no encontrado','trace_id'=>$trace],404);
            }

            $cred->delete();

            return response()->json([
                'ok'=>true,
                'rfc'=>$rfc,
                'trace_id'=>$trace
            ]);

        } catch (\Throwable $e) {
            Log::error('[SAT:RFC:delete]',['err'=>$e->getMessage()]);
            return response()->json(['ok'=>false,'msg'=>'No se pudo eliminar','trace_id'=>$trace],500);
        }
    }
}
