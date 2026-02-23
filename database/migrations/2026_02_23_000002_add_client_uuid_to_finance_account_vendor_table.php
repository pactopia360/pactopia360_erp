<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function conn(): string
    {
        return (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function up(): void
    {
        $c = $this->conn();

        if (!Schema::connection($c)->hasTable('finance_account_vendor')) {
            return;
        }

        Schema::connection($c)->table('finance_account_vendor', function (Blueprint $t) use ($c) {
            if (!Schema::connection($c)->hasColumn('finance_account_vendor', 'client_uuid')) {
                // UUID de cuentas_cliente.id (36)
                $t->char('client_uuid', 36)->nullable()->index()->after('account_id');
            }
        });

        // índice “try/catch”
        foreach ([
            ['finance_account_vendor_client_uuid_index', 'ALTER TABLE finance_account_vendor ADD INDEX finance_account_vendor_client_uuid_index (client_uuid)'],
        ] as [$idx, $sql]) {
            try { DB::connection($c)->statement($sql); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        $c = $this->conn();
        if (!Schema::connection($c)->hasTable('finance_account_vendor')) return;

        Schema::connection($c)->table('finance_account_vendor', function (Blueprint $t) use ($c) {
            if (Schema::connection($c)->hasColumn('finance_account_vendor', 'client_uuid')) {
                $t->dropColumn('client_uuid');
            }
        });
    }
};