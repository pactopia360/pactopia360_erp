<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        $this->patchBillingInvoiceRequests();
        $this->patchBillingInvoices();
        $this->createBillingInvoiceProfiles();
    }

    public function down(): void
    {
        // Patch seguro: no eliminamos columnas ni tablas para no perder datos.
    }

    private function patchBillingInvoiceRequests(): void
    {
        if (!Schema::connection($this->conn)->hasTable('billing_invoice_requests')) {
            return;
        }

        Schema::connection($this->conn)->table('billing_invoice_requests', function (Blueprint $table) {
            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'statement_id')) {
                $table->unsignedBigInteger('statement_id')->nullable()->after('period')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'invoice_profile_id')) {
                $table->unsignedBigInteger('invoice_profile_id')->nullable()->after('statement_id')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'invoice_id')) {
                $table->unsignedBigInteger('invoice_id')->nullable()->after('invoice_profile_id')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'tipo_comprobante')) {
                $table->string('tipo_comprobante', 5)->default('I')->after('status')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'metodo_pago')) {
                $table->string('metodo_pago', 10)->default('PPD')->after('tipo_comprobante')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'forma_pago')) {
                $table->string('forma_pago', 10)->default('99')->after('metodo_pago')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'uso_cfdi')) {
                $table->string('uso_cfdi', 10)->nullable()->after('forma_pago')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'regimen_fiscal')) {
                $table->string('regimen_fiscal', 10)->nullable()->after('uso_cfdi')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'scheduled_for')) {
                $table->date('scheduled_for')->nullable()->after('regimen_fiscal')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'scheduled_at')) {
                $table->timestamp('scheduled_at')->nullable()->after('scheduled_for')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'approved_at')) {
                $table->timestamp('approved_at')->nullable()->after('scheduled_at')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'issued_at')) {
                $table->timestamp('issued_at')->nullable()->after('approved_at')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('issued_at')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'last_error')) {
                $table->longText('last_error')->nullable()->after('notes');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'attempts')) {
                $table->unsignedSmallInteger('attempts')->default(0)->after('last_error');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'send_statement_pdf')) {
                $table->boolean('send_statement_pdf')->default(true)->after('attempts')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'send_cfdi_pdf')) {
                $table->boolean('send_cfdi_pdf')->default(true)->after('send_statement_pdf')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'send_cfdi_xml')) {
                $table->boolean('send_cfdi_xml')->default(true)->after('send_cfdi_pdf')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoice_requests', 'meta')) {
                $table->json('meta')->nullable()->after('send_cfdi_xml');
            }
        });
    }

    private function patchBillingInvoices(): void
    {
        if (!Schema::connection($this->conn)->hasTable('billing_invoices')) {
            return;
        }

        Schema::connection($this->conn)->table('billing_invoices', function (Blueprint $table) {
            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'request_id')) {
                $table->unsignedBigInteger('request_id')->nullable()->after('period')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'statement_id')) {
                $table->unsignedBigInteger('statement_id')->nullable()->after('request_id')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'invoice_profile_id')) {
                $table->unsignedBigInteger('invoice_profile_id')->nullable()->after('statement_id')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'source')) {
                $table->string('source', 60)->default('admin')->after('invoice_profile_id')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'tipo_comprobante')) {
                $table->string('tipo_comprobante', 5)->default('I')->after('source')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'metodo_pago')) {
                $table->string('metodo_pago', 10)->nullable()->after('tipo_comprobante')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'forma_pago')) {
                $table->string('forma_pago', 10)->nullable()->after('metodo_pago')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'uso_cfdi')) {
                $table->string('uso_cfdi', 10)->nullable()->after('forma_pago')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'regimen_fiscal')) {
                $table->string('regimen_fiscal', 10)->nullable()->after('uso_cfdi')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'rfc')) {
                $table->string('rfc', 20)->nullable()->after('cfdi_uuid')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'razon_social')) {
                $table->string('razon_social', 255)->nullable()->after('rfc');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'issued_at')) {
                $table->timestamp('issued_at')->nullable()->after('issued_date')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'disk')) {
                $table->string('disk', 20)->default('local')->after('currency');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'pdf_name')) {
                $table->string('pdf_name', 255)->nullable()->after('pdf_path');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'pdf_size')) {
                $table->unsignedBigInteger('pdf_size')->nullable()->after('pdf_name');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'pdf_sha1')) {
                $table->string('pdf_sha1', 40)->nullable()->after('pdf_size');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'xml_name')) {
                $table->string('xml_name', 255)->nullable()->after('xml_path');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'xml_size')) {
                $table->unsignedBigInteger('xml_size')->nullable()->after('xml_name');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'xml_sha1')) {
                $table->string('xml_sha1', 40)->nullable()->after('xml_size');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'pactopia_pdf_path')) {
                $table->string('pactopia_pdf_path', 500)->nullable()->after('xml_sha1');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'pactopia_pdf_name')) {
                $table->string('pactopia_pdf_name', 255)->nullable()->after('pactopia_pdf_path');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'sent_at')) {
                $table->timestamp('sent_at')->nullable()->after('status')->index();
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'emailed_to')) {
                $table->json('emailed_to')->nullable()->after('sent_at');
            }

            if (!Schema::connection($this->conn)->hasColumn('billing_invoices', 'meta')) {
                $table->json('meta')->nullable()->after('notes');
            }
        });
    }

    private function createBillingInvoiceProfiles(): void
    {
        if (Schema::connection($this->conn)->hasTable('billing_invoice_profiles')) {
            return;
        }

        Schema::connection($this->conn)->create('billing_invoice_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('account_id', 64)->index();
            $table->unsignedInteger('admin_account_id')->nullable()->index();
            $table->string('client_uuid', 64)->nullable()->index();

            $table->boolean('requires_invoice')->default(false)->index();
            $table->boolean('auto_generate_request')->default(false)->index();
            $table->boolean('auto_stamp')->default(false)->index();
            $table->boolean('auto_send')->default(false)->index();

            $table->string('invoice_mode', 30)->default('ppd_statement')->index();
            $table->string('tipo_comprobante', 5)->default('I')->index();
            $table->string('metodo_pago', 10)->default('PPD')->index();
            $table->string('forma_pago', 10)->default('99')->index();

            $table->string('uso_cfdi', 10)->nullable()->index();
            $table->string('regimen_fiscal', 10)->nullable()->index();

            $table->string('rfc', 20)->nullable()->index();
            $table->string('razon_social', 255)->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('email', 255)->nullable();
            $table->json('emails')->nullable();

            $table->unsignedTinyInteger('schedule_day')->nullable()->index();
            $table->string('schedule_time', 8)->nullable();
            $table->date('effective_from')->nullable()->index();
            $table->date('effective_until')->nullable()->index();

            $table->boolean('send_statement_pdf')->default(true)->index();
            $table->boolean('send_cfdi_pdf')->default(true)->index();
            $table->boolean('send_cfdi_xml')->default(true)->index();
            $table->boolean('send_pactopia_pdf')->default(true)->index();

            $table->string('status', 20)->default('active')->index();
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();

            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable()->index();

            $table->timestamps();

            $table->unique(['account_id'], 'billing_invoice_profiles_account_unique');
        });
    }
};