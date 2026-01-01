<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_clientes')->table('cuentas_cliente', function (Blueprint $t) {
            // meta para billing/modules (JSON si el driver lo soporta; Laravel lo mapeará)
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'meta')) {
                $t->json('meta')->nullable();
            }

            // por si tu tabla no tiene timestamps (no siempre aplica)
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'updated_at')) {
                $t->timestamp('updated_at')->nullable();
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'created_at')) {
                $t->timestamp('created_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('cuentas_cliente', function (Blueprint $t) {
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'meta')) {
                $t->dropColumn('meta');
            }
            // NO dropeo timestamps en down para evitar perder datos si ya existían.
        });
    }
};
