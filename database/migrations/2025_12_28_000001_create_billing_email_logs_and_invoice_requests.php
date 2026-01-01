<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        $this->ensureBillingEmailLogs();
        $this->ensureBillingInvoiceRequests();
    }

    public function down(): void
    {
        // Conservador: no dropeamos por defecto.
        // Schema::connection($this->conn)->dropIfExists('billing_invoice_requests');
        // Schema::connection($this->conn)->dropIfExists('billing_email_logs');
    }

    private function ensureBillingEmailLogs(): void
    {
        $sc = Schema::connection($this->conn);

        // 1) Intento crear si "no existe" (pero si por X razón ya existe, atrapamos 1050)
        if (!$sc->hasTable('billing_email_logs')) {
            try {
                $sc->create('billing_email_logs', function (Blueprint $t) {
                    $t->bigIncrements('id');

                    // ✅ ID público para tracking (OPEN/CLICK)
                    $t->string('email_id', 64)->unique(); // ulid/uuid

                    $t->unsignedBigInteger('statement_id')->index();
                    $t->string('email', 190)->index();

                    $t->string('template', 120)->default('statement')->index(); // statement|reminder|receipt
                    $t->string('status', 40)->default('queued')->index();       // queued|sent|failed

                    $t->string('provider', 60)->nullable();                     // smtp|mailgun|sendgrid|ses
                    $t->string('provider_message_id', 190)->nullable()->index();

                    $t->text('subject')->nullable();
                    $t->json('payload')->nullable();    // pdf_url, pay_url, portal_url, etc
                    $t->json('meta')->nullable();       // errores, headers, etc

                    $t->timestamp('queued_at')->nullable();
                    $t->timestamp('sent_at')->nullable();
                    $t->timestamp('failed_at')->nullable();

                    // Tracking
                    $t->unsignedInteger('open_count')->default(0);
                    $t->unsignedInteger('click_count')->default(0);
                    $t->timestamp('first_open_at')->nullable();
                    $t->timestamp('last_open_at')->nullable();
                    $t->timestamp('first_click_at')->nullable();
                    $t->timestamp('last_click_at')->nullable();

                    $t->timestamps();
                });

                return;
            } catch (QueryException $e) {
                // Si ya existe, seguimos con idempotente
                // SQLSTATE[42S01] / errno 1050
                // No re-lanzamos.
            }
        }

        // 2) Idempotente: asegurar columnas faltantes
        $sc->table('billing_email_logs', function (Blueprint $t) use ($sc) {

            // ✅ email_id (tracking)
            if (!$sc->hasColumn('billing_email_logs', 'email_id')) {
                // nullable para no depender de doctrine/dbal
                $t->string('email_id', 64)->nullable()->index();
            }

            // Columnas base
            if (!$sc->hasColumn('billing_email_logs', 'statement_id')) $t->unsignedBigInteger('statement_id')->nullable()->index();
            if (!$sc->hasColumn('billing_email_logs', 'email')) $t->string('email', 190)->nullable()->index();

            if (!$sc->hasColumn('billing_email_logs', 'template')) $t->string('template', 120)->default('statement')->index();
            if (!$sc->hasColumn('billing_email_logs', 'status')) $t->string('status', 40)->default('queued')->index();

            if (!$sc->hasColumn('billing_email_logs', 'provider')) $t->string('provider', 60)->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'provider_message_id')) $t->string('provider_message_id', 190)->nullable()->index();

            if (!$sc->hasColumn('billing_email_logs', 'subject')) $t->text('subject')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'payload')) $t->json('payload')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'meta')) $t->json('meta')->nullable();

            if (!$sc->hasColumn('billing_email_logs', 'queued_at')) $t->timestamp('queued_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'sent_at')) $t->timestamp('sent_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'failed_at')) $t->timestamp('failed_at')->nullable();

            // Tracking
            if (!$sc->hasColumn('billing_email_logs', 'open_count')) $t->unsignedInteger('open_count')->default(0);
            if (!$sc->hasColumn('billing_email_logs', 'click_count')) $t->unsignedInteger('click_count')->default(0);
            if (!$sc->hasColumn('billing_email_logs', 'first_open_at')) $t->timestamp('first_open_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'last_open_at')) $t->timestamp('last_open_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'first_click_at')) $t->timestamp('first_click_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'last_click_at')) $t->timestamp('last_click_at')->nullable();

            // timestamps
            if (!$sc->hasColumn('billing_email_logs', 'created_at')) $t->timestamp('created_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'updated_at')) $t->timestamp('updated_at')->nullable();
        });

        // 3) Backfill email_id si existe pero está null (tabla vieja)
        if ($sc->hasColumn('billing_email_logs', 'email_id')) {
            $rows = DB::connection($this->conn)->table('billing_email_logs')
                ->whereNull('email_id')
                ->orderBy('id')
                ->limit(5000)
                ->get(['id']);

            foreach ($rows as $r) {
                DB::connection($this->conn)->table('billing_email_logs')
                    ->where('id', (int)$r->id)
                    ->update([
                        'email_id' => (string) Str::ulid(),
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    private function ensureBillingInvoiceRequests(): void
    {
        $sc = Schema::connection($this->conn);

        if (!$sc->hasTable('billing_invoice_requests')) {
            try {
                $sc->create('billing_invoice_requests', function (Blueprint $t) {
                    $t->bigIncrements('id');

                    $t->unsignedBigInteger('statement_id')->index();
                    $t->string('account_id', 64)->index();
                    $t->string('period', 7)->index(); // YYYY-MM

                    $t->string('status', 40)->default('requested')->index(); // requested|in_progress|issued|rejected
                    $t->string('requested_by', 80)->nullable();              // system|client|admin:{id}
                    $t->text('notes')->nullable();

                    // CFDI info
                    $t->string('cfdi_uuid', 64)->nullable()->index();
                    $t->string('cfdi_folio', 64)->nullable();
                    $t->string('cfdi_url', 512)->nullable();

                    $t->timestamp('requested_at')->nullable();
                    $t->timestamp('issued_at')->nullable();

                    $t->json('meta')->nullable();

                    $t->timestamps();
                });

                return;
            } catch (QueryException $e) {
                // Si ya existe, seguimos.
            }
        }

        $sc->table('billing_invoice_requests', function (Blueprint $t) use ($sc) {
            if (!$sc->hasColumn('billing_invoice_requests', 'statement_id')) $t->unsignedBigInteger('statement_id')->nullable()->index();
            if (!$sc->hasColumn('billing_invoice_requests', 'account_id')) $t->string('account_id', 64)->nullable()->index();
            if (!$sc->hasColumn('billing_invoice_requests', 'period')) $t->string('period', 7)->nullable()->index();

            if (!$sc->hasColumn('billing_invoice_requests', 'status')) $t->string('status', 40)->default('requested')->index();
            if (!$sc->hasColumn('billing_invoice_requests', 'requested_by')) $t->string('requested_by', 80)->nullable();
            if (!$sc->hasColumn('billing_invoice_requests', 'notes')) $t->text('notes')->nullable();

            if (!$sc->hasColumn('billing_invoice_requests', 'cfdi_uuid')) $t->string('cfdi_uuid', 64)->nullable()->index();
            if (!$sc->hasColumn('billing_invoice_requests', 'cfdi_folio')) $t->string('cfdi_folio', 64)->nullable();
            if (!$sc->hasColumn('billing_invoice_requests', 'cfdi_url')) $t->string('cfdi_url', 512)->nullable();

            if (!$sc->hasColumn('billing_invoice_requests', 'requested_at')) $t->timestamp('requested_at')->nullable();
            if (!$sc->hasColumn('billing_invoice_requests', 'issued_at')) $t->timestamp('issued_at')->nullable();

            if (!$sc->hasColumn('billing_invoice_requests', 'meta')) $t->json('meta')->nullable();

            if (!$sc->hasColumn('billing_invoice_requests', 'created_at')) $t->timestamp('created_at')->nullable();
            if (!$sc->hasColumn('billing_invoice_requests', 'updated_at')) $t->timestamp('updated_at')->nullable();
        });
    }
};
