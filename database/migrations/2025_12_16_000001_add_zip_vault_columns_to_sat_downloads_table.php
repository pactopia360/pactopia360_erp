<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysql_clientes')->table('sat_downloads', function (Blueprint $table) {
            // 1) ¿En qué disco está el ZIP?
            if (!Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'zip_disk')) {
                $table->string('zip_disk', 32)->nullable()->after('attempts');
            }

            // 2) Bytes del ZIP (tamaño real)
            if (!Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'zip_bytes')) {
                $table->unsignedBigInteger('zip_bytes')->nullable()->after('zip_path');
            }

            // 3) Ruta en bóveda (si lo mueves a sat_vault)
            if (!Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'vault_path')) {
                $table->string('vault_path', 255)->nullable()->after('zip_path');
            }

            // 4) Marca de finalización (equivalente a finished_at)
            if (!Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'finished_at')) {
                $table->timestamp('finished_at')->nullable()->after('last_checked_at');
            }

            // 5) Opcional: cuándo fue descargado por última vez (útil para auditoría/soporte)
            if (!Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'downloaded_at')) {
                $table->timestamp('downloaded_at')->nullable()->after('finished_at');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('sat_downloads', function (Blueprint $table) {
            $cols = ['zip_disk', 'zip_bytes', 'vault_path', 'finished_at', 'downloaded_at'];

            foreach ($cols as $c) {
                if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', $c)) {
                    $table->dropColumn($c);
                }
            }
        });
    }
};
