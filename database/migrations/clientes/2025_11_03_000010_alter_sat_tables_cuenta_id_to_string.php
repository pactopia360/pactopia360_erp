<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function getConnection(): string { return 'mysql_clientes'; }

    public function up(): void
    {
        // sat_credentials
        if (Schema::connection($this->getConnection())->hasTable('sat_credentials')) {
            Schema::connection($this->getConnection())->table('sat_credentials', function (Blueprint $table) {
                // si era unsignedBigInteger, lo cambiamos a string(36)
                // Nota: para change() en MySQL puede requerir doctrine/dbal,
                // si no lo tienes, te doy plan B más abajo.
                if (Schema::connection($this->getConnection())->hasColumn('sat_credentials', 'cuenta_id')) {
                    $table->string('cuenta_id', 36)->change();
                }
            });
        }

        // sat_downloads
        if (Schema::connection($this->getConnection())->hasTable('sat_downloads')) {
            Schema::connection($this->getConnection())->table('sat_downloads', function (Blueprint $table) {
                if (Schema::connection($this->getConnection())->hasColumn('sat_downloads', 'cuenta_id')) {
                    $table->string('cuenta_id', 36)->change();
                }
            });
        }
    }

    public function down(): void
    {
        // No revertimos a bigint para evitar pérdida de datos
    }
};
