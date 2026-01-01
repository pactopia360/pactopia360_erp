<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        if (Schema::connection($this->conn)->hasTable('billing_invoices')) return;

        Schema::connection($this->conn)->create('billing_invoices', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('account_id', 64)->index();
            $t->string('period', 7)->index();

            $t->string('serie', 20)->nullable();
            $t->string('folio', 40)->nullable();
            $t->string('cfdi_uuid', 80)->nullable()->index();

            $t->date('issued_date')->nullable()->index();
            $t->integer('amount_cents')->default(0);
            $t->string('currency', 10)->default('MXN');

            $t->string('pdf_path', 255)->nullable();
            $t->string('xml_path', 255)->nullable();

            $t->string('status', 40)->default('issued')->index(); // issued|void|credit_note
            $t->longText('notes')->nullable();

            $t->timestamps();

            $t->unique(['account_id','period'], 'bi_account_period_uq');
        });
    }

    public function down(): void
    {
        // Schema::connection($this->conn)->dropIfExists('billing_invoices');
    }
};
