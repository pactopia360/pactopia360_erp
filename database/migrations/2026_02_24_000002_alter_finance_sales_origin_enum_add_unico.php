<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Tabla: finance_sales (mysql_admin)
     *
     * Motivo:
     * - UI y servicios manejan "unico" como origen.
     * - BD hoy solo permite enum('recurrente','no_recurrente') y truena si llega "unico".
     *
     * Fix:
     * - Ampliar enum para aceptar 'unico' sin afectar datos existentes.
     */
    public function up(): void
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!Schema::connection($adm)->hasTable('finance_sales')) {
            return;
        }

        // MySQL ENUM: alterar con statement
        DB::connection($adm)->statement("
            ALTER TABLE `finance_sales`
            MODIFY `origin` ENUM('recurrente','no_recurrente','unico')
            NOT NULL DEFAULT 'no_recurrente'
        ");
    }

    public function down(): void
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!Schema::connection($adm)->hasTable('finance_sales')) {
            return;
        }

        // Al revertir, normalizamos cualquier 'unico' a 'no_recurrente' para no romper el enum viejo
        DB::connection($adm)->statement("
            UPDATE `finance_sales`
            SET `origin` = 'no_recurrente'
            WHERE `origin` = 'unico'
        ");

        DB::connection($adm)->statement("
            ALTER TABLE `finance_sales`
            MODIFY `origin` ENUM('recurrente','no_recurrente')
            NOT NULL DEFAULT 'no_recurrente'
        ");
    }
};