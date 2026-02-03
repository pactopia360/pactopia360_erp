<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // OJO: esta tabla está en mysql_clientes (p360v1_clientes)
        Schema::connection('mysql_clientes')->table('external_fiel_uploads', function (Blueprint $table) {
            if (!Schema::connection('mysql_clientes')->hasColumn('external_fiel_uploads', 'cuenta_id')) {
                $table->char('cuenta_id', 36)->nullable()->after('id');
                $table->index(['cuenta_id', 'status'], 'idx_fiel_cuenta_status');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('external_fiel_uploads', function (Blueprint $table) {
            if (Schema::connection('mysql_clientes')->hasColumn('external_fiel_uploads', 'cuenta_id')) {
                // intenta borrar índice si existe
                try { $table->dropIndex('idx_fiel_cuenta_status'); } catch (\Throwable) {}
                $table->dropColumn('cuenta_id');
            }
        });
    }
};
