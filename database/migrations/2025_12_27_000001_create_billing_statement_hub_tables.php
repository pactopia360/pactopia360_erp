<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        $this->ensureBillingStatementMeta();
        $this->ensureBillingEmailLogs();
        $this->ensureBillingEmailEvents();
    }

    public function down(): void
    {
        // Conservador: si quieres drop total, descomenta.
        // Schema::connection($this->conn)->dropIfExists('billing_email_events');
        // Schema::connection($this->conn)->dropIfExists('billing_email_logs');
        // Schema::connection($this->conn)->dropIfExists('billing_statement_meta');
    }

    private function ensureBillingStatementMeta(): void
    {
        $sc = Schema::connection($this->conn);

        if (!$sc->hasTable('billing_statement_meta')) {
            $sc->create('billing_statement_meta', function (Blueprint $t) {
                $t->bigIncrements('id');

                $t->uuid('account_id')->index();
                $t->string('period', 7)->index(); // YYYY-MM

                $t->decimal('expected_total_mxn', 12, 2)->default(0);
                $t->decimal('cargo_total_mxn', 12, 2)->default(0);
                $t->decimal('abono_total_mxn', 12, 2)->default(0);
                $t->decimal('saldo_total_mxn', 12, 2)->default(0);

                $t->string('status', 30)->default('pendiente')->index();

                $t->string('pay_provider', 20)->nullable()->index();
                $t->string('pay_session_id', 255)->nullable()->index();
                $t->text('pay_url')->nullable();
                $t->timestamp('pay_link_created_at')->nullable();

                $t->string('last_email_id', 36)->nullable()->index();
                $t->string('last_email_to', 190)->nullable();
                $t->string('last_email_subject', 255)->nullable();
                $t->timestamp('last_sent_at')->nullable();

                $t->timestamp('first_opened_at')->nullable();
                $t->timestamp('last_opened_at')->nullable();
                $t->unsignedInteger('open_count')->default(0);

                $t->timestamp('first_clicked_at')->nullable();
                $t->timestamp('last_clicked_at')->nullable();
                $t->unsignedInteger('click_count')->default(0);

                $t->timestamp('invoice_requested_at')->nullable();
                $t->string('invoice_status', 30)->nullable()->index();
                $t->text('invoice_note')->nullable();

                $t->timestamp('next_scheduled_send_at')->nullable()->index();
                $t->boolean('auto_send_enabled')->default(true);

                $t->json('meta')->nullable();

                $t->timestamps();

                $t->unique(['account_id', 'period'], 'uniq_bsm_account_period');
            });

            return;
        }

        // Tabla existe: agregar columnas faltantes (idempotente)
        $sc->table('billing_statement_meta', function (Blueprint $t) use ($sc) {

            if (!$sc->hasColumn('billing_statement_meta', 'account_id')) $t->uuid('account_id')->nullable()->index();
            if (!$sc->hasColumn('billing_statement_meta', 'period')) $t->string('period', 7)->nullable()->index();

            if (!$sc->hasColumn('billing_statement_meta', 'expected_total_mxn')) $t->decimal('expected_total_mxn', 12, 2)->default(0);
            if (!$sc->hasColumn('billing_statement_meta', 'cargo_total_mxn')) $t->decimal('cargo_total_mxn', 12, 2)->default(0);
            if (!$sc->hasColumn('billing_statement_meta', 'abono_total_mxn')) $t->decimal('abono_total_mxn', 12, 2)->default(0);
            if (!$sc->hasColumn('billing_statement_meta', 'saldo_total_mxn')) $t->decimal('saldo_total_mxn', 12, 2)->default(0);

            if (!$sc->hasColumn('billing_statement_meta', 'status')) $t->string('status', 30)->default('pendiente')->index();

            if (!$sc->hasColumn('billing_statement_meta', 'pay_provider')) $t->string('pay_provider', 20)->nullable()->index();
            if (!$sc->hasColumn('billing_statement_meta', 'pay_session_id')) $t->string('pay_session_id', 255)->nullable()->index();
            if (!$sc->hasColumn('billing_statement_meta', 'pay_url')) $t->text('pay_url')->nullable();
            if (!$sc->hasColumn('billing_statement_meta', 'pay_link_created_at')) $t->timestamp('pay_link_created_at')->nullable();

            if (!$sc->hasColumn('billing_statement_meta', 'last_email_id')) $t->string('last_email_id', 36)->nullable()->index();
            if (!$sc->hasColumn('billing_statement_meta', 'last_email_to')) $t->string('last_email_to', 190)->nullable();
            if (!$sc->hasColumn('billing_statement_meta', 'last_email_subject')) $t->string('last_email_subject', 255)->nullable();
            if (!$sc->hasColumn('billing_statement_meta', 'last_sent_at')) $t->timestamp('last_sent_at')->nullable();

            if (!$sc->hasColumn('billing_statement_meta', 'first_opened_at')) $t->timestamp('first_opened_at')->nullable();
            if (!$sc->hasColumn('billing_statement_meta', 'last_opened_at')) $t->timestamp('last_opened_at')->nullable();
            if (!$sc->hasColumn('billing_statement_meta', 'open_count')) $t->unsignedInteger('open_count')->default(0);

            if (!$sc->hasColumn('billing_statement_meta', 'first_clicked_at')) $t->timestamp('first_clicked_at')->nullable();
            if (!$sc->hasColumn('billing_statement_meta', 'last_clicked_at')) $t->timestamp('last_clicked_at')->nullable();
            if (!$sc->hasColumn('billing_statement_meta', 'click_count')) $t->unsignedInteger('click_count')->default(0);

            if (!$sc->hasColumn('billing_statement_meta', 'invoice_requested_at')) $t->timestamp('invoice_requested_at')->nullable();
            if (!$sc->hasColumn('billing_statement_meta', 'invoice_status')) $t->string('invoice_status', 30)->nullable()->index();
            if (!$sc->hasColumn('billing_statement_meta', 'invoice_note')) $t->text('invoice_note')->nullable();

            if (!$sc->hasColumn('billing_statement_meta', 'next_scheduled_send_at')) $t->timestamp('next_scheduled_send_at')->nullable()->index();
            if (!$sc->hasColumn('billing_statement_meta', 'auto_send_enabled')) $t->boolean('auto_send_enabled')->default(true);

            if (!$sc->hasColumn('billing_statement_meta', 'meta')) $t->json('meta')->nullable();

            if (!$sc->hasColumn('billing_statement_meta', 'created_at')) $t->timestamp('created_at')->nullable();
            if (!$sc->hasColumn('billing_statement_meta', 'updated_at')) $t->timestamp('updated_at')->nullable();
        });

        // Unique index: si ya existe con ese nombre no lo recreamos.
        // Nota: verificar índices por nombre portable no es trivial sin DB::select; lo dejamos en “best effort”:
        // si tu tabla ya tiene uniq, perfecto; si no, lo agregas manual con SQL.
    }

    private function ensureBillingEmailLogs(): void
    {
        $sc = Schema::connection($this->conn);

        if (!$sc->hasTable('billing_email_logs')) {
            $sc->create('billing_email_logs', function (Blueprint $t) {
                $t->bigIncrements('id');

                $t->uuid('email_id')->unique();
                $t->uuid('account_id')->index();
                $t->string('period', 7)->index();

                $t->string('to', 190);
                $t->string('subject', 255);
                $t->string('template', 120)->default('admin.mail.statement');

                $t->string('status', 30)->default('queued')->index();
                $t->text('error')->nullable();

                $t->timestamp('sent_at')->nullable();
                $t->timestamp('first_opened_at')->nullable();
                $t->timestamp('last_opened_at')->nullable();
                $t->unsignedInteger('open_count')->default(0);

                $t->timestamp('first_clicked_at')->nullable();
                $t->timestamp('last_clicked_at')->nullable();
                $t->unsignedInteger('click_count')->default(0);

                $t->text('pay_url')->nullable();
                $t->string('pay_provider', 20)->nullable();
                $t->string('pay_session_id', 255)->nullable();

                $t->json('payload')->nullable();
                $t->timestamps();
            });

            return;
        }

        // Tabla existe: agregar faltantes (idempotente)
        $sc->table('billing_email_logs', function (Blueprint $t) use ($sc) {

            if (!$sc->hasColumn('billing_email_logs', 'email_id')) $t->uuid('email_id')->nullable()->unique();
            if (!$sc->hasColumn('billing_email_logs', 'account_id')) $t->uuid('account_id')->nullable()->index();
            if (!$sc->hasColumn('billing_email_logs', 'period')) $t->string('period', 7)->nullable()->index();

            if (!$sc->hasColumn('billing_email_logs', 'to')) $t->string('to', 190)->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'subject')) $t->string('subject', 255)->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'template')) $t->string('template', 120)->default('admin.mail.statement');

            if (!$sc->hasColumn('billing_email_logs', 'status')) $t->string('status', 30)->default('queued')->index();
            if (!$sc->hasColumn('billing_email_logs', 'error')) $t->text('error')->nullable();

            if (!$sc->hasColumn('billing_email_logs', 'sent_at')) $t->timestamp('sent_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'first_opened_at')) $t->timestamp('first_opened_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'last_opened_at')) $t->timestamp('last_opened_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'open_count')) $t->unsignedInteger('open_count')->default(0);

            if (!$sc->hasColumn('billing_email_logs', 'first_clicked_at')) $t->timestamp('first_clicked_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'last_clicked_at')) $t->timestamp('last_clicked_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'click_count')) $t->unsignedInteger('click_count')->default(0);

            if (!$sc->hasColumn('billing_email_logs', 'pay_url')) $t->text('pay_url')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'pay_provider')) $t->string('pay_provider', 20)->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'pay_session_id')) $t->string('pay_session_id', 255)->nullable();

            if (!$sc->hasColumn('billing_email_logs', 'payload')) $t->json('payload')->nullable();

            if (!$sc->hasColumn('billing_email_logs', 'created_at')) $t->timestamp('created_at')->nullable();
            if (!$sc->hasColumn('billing_email_logs', 'updated_at')) $t->timestamp('updated_at')->nullable();
        });
    }

    private function ensureBillingEmailEvents(): void
    {
        $sc = Schema::connection($this->conn);

        if (!$sc->hasTable('billing_email_events')) {
            $sc->create('billing_email_events', function (Blueprint $t) {
                $t->bigIncrements('id');

                $t->uuid('email_id')->index();
                $t->string('event', 20)->index(); // open|click
                $t->string('ip', 80)->nullable();
                $t->string('ua', 255)->nullable();
                $t->string('ref', 255)->nullable();

                $t->timestamps();
            });

            return;
        }

        $sc->table('billing_email_events', function (Blueprint $t) use ($sc) {

            if (!$sc->hasColumn('billing_email_events', 'email_id')) $t->uuid('email_id')->nullable()->index();
            if (!$sc->hasColumn('billing_email_events', 'event')) $t->string('event', 20)->nullable()->index();
            if (!$sc->hasColumn('billing_email_events', 'ip')) $t->string('ip', 80)->nullable();
            if (!$sc->hasColumn('billing_email_events', 'ua')) $t->string('ua', 255)->nullable();
            if (!$sc->hasColumn('billing_email_events', 'ref')) $t->string('ref', 255)->nullable();

            if (!$sc->hasColumn('billing_email_events', 'created_at')) $t->timestamp('created_at')->nullable();
            if (!$sc->hasColumn('billing_email_events', 'updated_at')) $t->timestamp('updated_at')->nullable();
        });
    }
};
