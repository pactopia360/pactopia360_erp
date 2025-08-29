<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::connection('mysql_clientes')->table('clientes', function (Blueprint $table) {
            if (!Schema::connection('mysql_clientes')->hasColumn('clientes','bloqueado')) {
                $table->boolean('bloqueado')->default(false)->after('estado');
                $table->timestamp('bloqueado_desde')->nullable()->after('bloqueado');
                $table->string('bloqueo_motivo',120)->nullable()->after('bloqueado_desde');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('clientes','plan')) {
                $table->string('plan',20)->default('free')->after('estado');
            }
        });
    }
    public function down(): void {}
};
