<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_clientes')->table('sat_downloads', function (Blueprint $table) {
            if (!Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'zip_disk')) {
                $table->string('zip_disk', 50)->nullable()->after('zip_path')->index();
            }

            if (!Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'zip_bytes')) {
                $table->unsignedBigInteger('zip_bytes')->nullable()->after('size_bytes');
            }

            if (!Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'vault_path')) {
                $table->string('vault_path', 255)->nullable()->after('zip_path')->index();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('sat_downloads', function (Blueprint $table) {
            if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'zip_disk')) {
                $table->dropColumn('zip_disk');
            }
            if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'zip_bytes')) {
                $table->dropColumn('zip_bytes');
            }
            if (Schema::connection('mysql_clientes')->hasColumn('sat_downloads', 'vault_path')) {
                $table->dropColumn('vault_path');
            }
        });
    }
};
