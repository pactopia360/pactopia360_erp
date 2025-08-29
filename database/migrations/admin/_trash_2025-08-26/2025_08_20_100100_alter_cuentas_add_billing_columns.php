<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::connection('mysql_admin')->table('cuentas', function (Blueprint $table) {
            if (!Schema::connection('mysql_admin')->hasColumn('cuentas','licencia')) {
                $table->enum('licencia',['free','pro'])->default('free')->after('estado');
            }
            if (!Schema::connection('mysql_admin')->hasColumn('cuentas','ciclo')) {
                $table->enum('ciclo',['mensual','anual'])->default('mensual')->after('licencia');
            }
            if (!Schema::connection('mysql_admin')->hasColumn('cuentas','proximo_corte')) {
                $table->date('proximo_corte')->nullable()->after('ciclo');
            }
            if (!Schema::connection('mysql_admin')->hasColumn('cuentas','bloqueado')) {
                $table->boolean('bloqueado')->default(false)->after('proximo_corte');
                $table->timestamp('bloqueado_desde')->nullable()->after('bloqueado');
                $table->string('bloqueo_motivo',120)->nullable()->after('bloqueado_desde');
            }
            if (!Schema::connection('mysql_admin')->hasColumn('cuentas','hits_asignados')) {
                $table->unsignedInteger('hits_asignados')->default(0)->after('espacio_mb');
            }
            if (!Schema::connection('mysql_admin')->hasColumn('cuentas','codigo_cliente')) {
                $table->string('codigo_cliente')->unique()->change();
            }
        });
    }
    public function down(): void {
        // No se eliminan para no romper históricos
    }
};
