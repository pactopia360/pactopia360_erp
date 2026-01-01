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
        $table = 'email_verifications';

        // Idempotencia: si ya existe, no truena
        if (Schema::connection($conn)->hasTable($table)) {
            // Best-effort: asegurar índices/unique si faltaran (sin romper si ya existen)
            try {
                Schema::connection($conn)->table($table, function (Blueprint $t) {
                    // MySQL: crear índices con nombres explícitos puede fallar si ya existen
                    // Token unique
                    $t->unique('token', 'email_verifications_token_unique');
                });
            } catch (\Throwable $e) {
                // ignorar si ya existía
            }

            try {
                Schema::connection($conn)->table($table, function (Blueprint $t) {
                    $t->index('account_id', 'email_verifications_account_id_index');
                });
            } catch (\Throwable $e) {
                // ignorar si ya existía
            }

            try {
                Schema::connection($conn)->table($table, function (Blueprint $t) {
                    $t->index('email', 'email_verifications_email_index');
                });
            } catch (\Throwable $e) {
                // ignorar si ya existía
            }

            return;
        }

        Schema::connection($conn)->create($table, function (Blueprint $t) {
            $t->id();

            // OJO: en tu error aparece account_id BIGINT. En clientes normalmente manejas UUID.
            // Mantengo BIGINT para no romper si ya existe lógica/relación así.
            $t->unsignedBigInteger('account_id')->index();

            $t->string('email', 150)->index();
            $t->string('token', 64)->unique();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('email_verifications');
    }
};
