<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Esta migración vive en /clientes, así que debe ejecutar en mysql_clientes
        $conn  = 'mysql_clientes';
        $table = 'phone_otps';

        // Idempotencia: si ya existe, no truena
        if (Schema::connection($conn)->hasTable($table)) {
            // Best-effort: asegurar índices (sin romper si ya existen)
            try {
                Schema::connection($conn)->table($table, function (Blueprint $t) {
                    $t->index('account_id', 'phone_otps_account_id_index');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::connection($conn)->table($table, function (Blueprint $t) {
                    $t->index('phone', 'phone_otps_phone_index');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::connection($conn)->table($table, function (Blueprint $t) {
                    $t->index('code', 'phone_otps_code_index');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::connection($conn)->table($table, function (Blueprint $t) {
                    $t->index('otp', 'phone_otps_otp_index');
                });
            } catch (\Throwable $e) {}

            return;
        }

        Schema::connection($conn)->create($table, function (Blueprint $t) {
            $t->id();

            // Mantengo BIGINT para compat con tu esquema actual (según el error)
            $t->unsignedBigInteger('account_id')->index();

            $t->string('phone', 32)->index();
            $t->string('code', 10)->index(); // duplicamos en 'otp' por compat
            $t->string('otp', 10)->nullable()->index();

            $t->enum('channel', ['sms', 'whatsapp'])->default('whatsapp');
            $t->unsignedTinyInteger('attempts')->default(0);

            $t->timestamp('expires_at')->nullable();
            $t->timestamp('used_at')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('phone_otps');
    }
};
