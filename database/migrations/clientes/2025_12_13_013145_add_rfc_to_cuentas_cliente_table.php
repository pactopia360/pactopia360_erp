<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysql_clientes')->table('cuentas_cliente', function (Blueprint $table) {
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'rfc')) {
                $table->string('rfc', 13)->nullable()->after('id');
                // Si debe ser único por cuenta, puedes activar esto cuando ya tengas datos limpios:
                // $table->unique('rfc', 'uq_cuentas_cliente_rfc');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('cuentas_cliente', function (Blueprint $table) {
            if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'rfc')) {
                // Si agregaste unique, primero dropea el índice:
                // $table->dropUnique('uq_cuentas_cliente_rfc');
                $table->dropColumn('rfc');
            }
        });
    }
};
