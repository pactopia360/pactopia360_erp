<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::connection('mysql_clientes')->table('cuentas_cliente', function (Blueprint $t) {
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'customer_no')) {
                $t->string('customer_no', 32)->nullable()->after('codigo_cliente');
            } else {
                $t->string('customer_no', 32)->nullable()->change();
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'espacio_asignado_mb')) {
                $t->integer('espacio_asignado_mb')->default(512);
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'hits_asignados')) {
                $t->integer('hits_asignados')->default(5);
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'max_usuarios')) {
                $t->integer('max_usuarios')->default(1);
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'max_empresas')) {
                $t->integer('max_empresas')->default(9999);
            }
        });
    }
    public function down(): void {
        // opcional: no tires columnas si ya hay datos
    }
};
