<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        // ==============================
        // billing_invoice_requests
        // ==============================
        if (Schema::connection($adm)->hasTable('billing_invoice_requests')) {
            Schema::connection($adm)->table('billing_invoice_requests', function (Blueprint $table) use ($adm) {
                // UNIQUE (account_id, period) evita duplicados reales
                // Nota: solo crear si no existe (Laravel no trae "if not exists" nativo para índices)
                // -> lo manejamos fuera con try/catch
            });

            try {
                Schema::connection($adm)->table('billing_invoice_requests', function (Blueprint $table) {
                    $table->unique(['account_id', 'period'], 'bir_account_period_unique');
                });
            } catch (\Throwable $e) {
                // si ya existe, ignora
            }

            // (Opcional) índice para listados por cuenta/periodo/status
            try {
                Schema::connection($adm)->table('billing_invoice_requests', function (Blueprint $table) {
                    $table->index(['account_id', 'period', 'status'], 'bir_account_period_status_idx');
                });
            } catch (\Throwable $e) {
                // ignora
            }
        }

        // ==============================
        // payments
        // ==============================
        if (Schema::connection($adm)->hasTable('payments')) {
            // Tu código consulta:
            // - where account_id + period
            // - a veces filtra status y ordena por fechas (paid_at/updated_at/created_at)
            try {
                Schema::connection($adm)->table('payments', function (Blueprint $table) {
                    $table->index(['account_id', 'period'], 'pay_account_period_idx');
                });
            } catch (\Throwable $e) {
                // ignora
            }

            try {
                Schema::connection($adm)->table('payments', function (Blueprint $table) {
                    $table->index(['account_id', 'period', 'status'], 'pay_account_period_status_idx');
                });
            } catch (\Throwable $e) {
                // ignora
            }
        }
    }

    public function down(): void
    {
        $adm = (string) config('p360.conn.admin', 'mysql_admin');

        if (Schema::connection($adm)->hasTable('billing_invoice_requests')) {
            try {
                Schema::connection($adm)->table('billing_invoice_requests', function (Blueprint $table) {
                    $table->dropUnique('bir_account_period_unique');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::connection($adm)->table('billing_invoice_requests', function (Blueprint $table) {
                    $table->dropIndex('bir_account_period_status_idx');
                });
            } catch (\Throwable $e) {}
        }

        if (Schema::connection($adm)->hasTable('payments')) {
            try {
                Schema::connection($adm)->table('payments', function (Blueprint $table) {
                    $table->dropIndex('pay_account_period_idx');
                });
            } catch (\Throwable $e) {}

            try {
                Schema::connection($adm)->table('payments', function (Blueprint $table) {
                    $table->dropIndex('pay_account_period_status_idx');
                });
            } catch (\Throwable $e) {}
        }
    }
};
