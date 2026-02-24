<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {

    public function up(): void
    {
        $cli = (string) (config('p360.conn.clientes') ?: 'mysql_clientes');

        if (!Schema::connection($cli)->hasTable('cuentas_cliente')) return;

        if (!Schema::connection($cli)->hasColumn('cuentas_cliente', 'is_blocked')) {
            Schema::connection($cli)->table('cuentas_cliente', function (Blueprint $t) {
                // 0 = desbloqueado, 1 = bloqueado (Stripe)
                $t->tinyInteger('is_blocked')->default(0)->after('activo');
                $t->index('is_blocked');
            });
        }
    }

    public function down(): void
    {
        $cli = (string) (config('p360.conn.clientes') ?: 'mysql_clientes');

        if (!Schema::connection($cli)->hasTable('cuentas_cliente')) return;

        if (Schema::connection($cli)->hasColumn('cuentas_cliente', 'is_blocked')) {
            Schema::connection($cli)->table('cuentas_cliente', function (Blueprint $t) {
                $t->dropIndex(['is_blocked']);
                $t->dropColumn('is_blocked');
            });
        }
    }
};