<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->connection;

        if (!Schema::connection($conn)->hasTable('sat_credentials')) {
            return;
        }

        Schema::connection($conn)->table('sat_credentials', function (Blueprint $table) use ($conn) {

            // ✅ Si tu esquema NO trae validated_at, lo agregamos (sin romper)
            if (!Schema::connection($conn)->hasColumn('sat_credentials', 'validated_at')) {
                $table->timestamp('validated_at')->nullable();
            }

            // ✅ Ya NO usamos after('validated_at') para evitar este error en cualquier BD
            if (!Schema::connection($conn)->hasColumn('sat_credentials', 'auto_download')) {
                $table->boolean('auto_download')->default(true);
            }

            // (Opcional pero recomendado si tu app usa alertas)
            if (!Schema::connection($conn)->hasColumn('sat_credentials', 'alert_email')) {
                $table->boolean('alert_email')->default(true);
            }
            if (!Schema::connection($conn)->hasColumn('sat_credentials', 'alert_whatsapp')) {
                $table->boolean('alert_whatsapp')->default(false);
            }
            if (!Schema::connection($conn)->hasColumn('sat_credentials', 'alert_inapp')) {
                $table->boolean('alert_inapp')->default(true);
            }
            if (!Schema::connection($conn)->hasColumn('sat_credentials', 'last_alert_at')) {
                $table->timestamp('last_alert_at')->nullable();
            }
        });
    }

    public function down(): void
    {
        // No revertimos en este proyecto (migración safe). Si quieres, lo hacemos luego.
    }
};
