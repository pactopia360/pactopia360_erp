<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (Schema::connection($adm)->hasTable('billing_invoices')) {
            return;
        }

        Schema::connection($adm)->create('billing_invoices', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->string('account_id', 36)->index();
            $t->string('period', 7)->index(); // YYYY-MM

            $t->unsignedBigInteger('request_id')->nullable()->index(); // id de invoice_requests o billing_invoice_requests
            $t->string('source', 12)->default('admin'); // admin|system|import

            $t->string('cfdi_uuid', 80)->nullable()->index();
            $t->string('rfc', 20)->nullable()->index();
            $t->string('razon_social', 255)->nullable();

            $t->string('status', 20)->default('active'); // active|void|replaced
            $t->timestamp('issued_at')->nullable();

            // Archivos
            $t->string('disk', 20)->default('local');

            $t->string('pdf_path', 500)->nullable();
            $t->string('pdf_name', 255)->nullable();
            $t->unsignedBigInteger('pdf_size')->nullable();
            $t->string('pdf_sha1', 40)->nullable();

            $t->string('xml_path', 500)->nullable();
            $t->string('xml_name', 255)->nullable();
            $t->unsignedBigInteger('xml_size')->nullable();
            $t->string('xml_sha1', 40)->nullable();

            $t->text('notes')->nullable();

            $t->timestamps();

            // Evita duplicados por cuenta+periodo+uuid (si uuid existe)
            $t->unique(['account_id','period','cfdi_uuid'], 'uniq_invoice_account_period_uuid');
        });
    }

    public function down(): void
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        Schema::connection($adm)->dropIfExists('billing_invoices');
    }
};