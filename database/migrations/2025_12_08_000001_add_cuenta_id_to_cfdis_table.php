<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Ojo: usamos connection mysql_clientes
        Schema::connection('mysql_clientes')->table('cfdis', function (Blueprint $table) {
            if (!Schema::connection('mysql_clientes')->hasColumn('cfdis', 'cuenta_id')) {
                $table->uuid('cuenta_id')->nullable()->after('id')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('cfdis', function (Blueprint $table) {
            if (Schema::connection('mysql_clientes')->hasColumn('cfdis', 'cuenta_id')) {
                $table->dropColumn('cuenta_id');
            }
        });
    }
};
