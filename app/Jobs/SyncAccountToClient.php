<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

class SyncAccountToClient implements ShouldQueue
{
    use Dispatchable, Queueable;

    public function __construct(public int $accountId) {}

    public function handle(): void
    {
        $admin = DB::connection('mysql_admin')->table('accounts')->where('id',$this->accountId)->first();
        if (!$admin) return;

        // upsert espejo (ajusta columnas reales)
        DB::connection('mysql_clientes')->table('usuarios_cliente')->updateOrInsert(
            ['rfc' => $admin->rfc],
            [
                'email'       => $admin->email,
                'customer_code'=> $admin->customer_code,
                'name'        => $admin->name ?? $admin->empresa ?? '',
                'updated_at'  => now(),
            ]
        );
    }
}
