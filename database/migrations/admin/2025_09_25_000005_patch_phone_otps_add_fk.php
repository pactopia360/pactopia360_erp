<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('phone_otps')) {
            return;
        }

        // 1) Agregar account_id si no existe (nullable porque puede no resolverse siempre)
        if (!Schema::connection($this->connection)->hasColumn('phone_otps', 'account_id')) {
            Schema::connection($this->connection)->table('phone_otps', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('id')->index();
            });
        }

        // 2) Intentar crear FK (opcional). Envuelto en try/catch por permisos.
        try {
            Schema::connection($this->connection)->table('phone_otps', function (Blueprint $table) {
                try {
                    $table->foreign('account_id')
                          ->references('id')->on('accounts')
                          ->onDelete('cascade');
                } catch (\Throwable $e) {
                    // Si no hay permisos o ya existe, lo ignoramos.
                }
            });
        } catch (\Throwable $e) {
            // Ignorar si no permite alterar FKs
        }
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable('phone_otps')) {
            return;
        }
        try {
            Schema::connection($this->connection)->table('phone_otps', function (Blueprint $table) {
                try { $table->dropForeign(['account_id']); } catch (\Throwable $e) {}
                if (Schema::connection($this->connection)->hasColumn('phone_otps', 'account_id')) {
                    $table->dropColumn('account_id');
                }
            });
        } catch (\Throwable $e) {}
    }
};
