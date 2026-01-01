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
        if (Schema::connection($this->conn)->hasTable('billing_invoice_requests')) return;

        Schema::connection($this->conn)->create('billing_invoice_requests', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('account_id', 64)->index();
            $t->string('period', 7)->index();
            $t->string('status', 40)->default('requested')->index(); // requested|issued|rejected
            $t->string('cfdi_uuid', 80)->nullable()->index();
            $t->longText('notes')->nullable();
            $t->timestamps();

            $t->unique(['account_id','period'], 'bir_account_period_uq');
        });
    }

    public function down(): void
    {
        // Schema::connection($this->conn)->dropIfExists('billing_invoice_requests');
    }
};
