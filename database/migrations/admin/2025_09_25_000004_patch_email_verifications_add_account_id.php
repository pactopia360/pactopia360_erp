<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        // Asegúrate de que la tabla exista
        if (!Schema::connection($this->connection)->hasTable('email_verifications')) {
            return;
        }

        // 1) Agregar columna account_id (nullable) si no existe
        if (!Schema::connection($this->connection)->hasColumn('email_verifications', 'account_id')) {
            Schema::connection($this->connection)->table('email_verifications', function (Blueprint $table) {
                $table->unsignedBigInteger('account_id')->nullable()->after('id')->index();
            });
        }

        // 2) Intentar backfill por email -> accounts.id
        try {
            DB::connection($this->connection)->statement("
                UPDATE email_verifications ev
                JOIN accounts a ON a.email = ev.email
                SET ev.account_id = a.id
                WHERE ev.account_id IS NULL
            ");
        } catch (\Throwable $e) {
            // Si no se puede (por permisos o falta de columnas), lo ignoramos.
        }

        // 3) Intentar crear FK si la columna existe y la tabla accounts también
        try {
            // Evitar crear dos veces la FK
            $sm = Schema::connection($this->connection);
            $sm->table('email_verifications', function (Blueprint $table) {
                // No hay método hasForeignKey nativo, envolvemos en try/catch
                try {
                    $table->foreign('account_id')
                          ->references('id')->on('accounts')
                          ->onDelete('cascade');
                } catch (\Throwable $e) {
                    // Si ya existe, o no hay permisos, lo ignoramos.
                }
            });
        } catch (\Throwable $e) {
            // Ignorar si el proveedor no permite alterar FKs
        }

        // 4) Asegurar columnas que vimos en tu tabla (por si faltaran en otros entornos)
        if (!Schema::connection($this->connection)->hasColumn('email_verifications', 'expires_at')) {
            Schema::connection($this->connection)->table('email_verifications', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('token');
            });
        }
        if (!Schema::connection($this->connection)->hasColumn('email_verifications', 'used')) {
            Schema::connection($this->connection)->table('email_verifications', function (Blueprint $table) {
                $table->boolean('used')->default(false)->after('expires_at');
            });
        }
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable('email_verifications')) {
            return;
        }
        // Quitar FK y columna (si aplica)
        try {
            Schema::connection($this->connection)->table('email_verifications', function (Blueprint $table) {
                try { $table->dropForeign(['account_id']); } catch (\Throwable $e) {}
                if (Schema::connection($this->connection)->hasColumn('email_verifications', 'account_id')) {
                    $table->dropColumn('account_id');
                }
            });
        } catch (\Throwable $e) {}
    }
};
