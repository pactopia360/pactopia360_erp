<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (Schema::connection($conn)->hasTable('finance_sales')) {
            return;
        }

        Schema::connection($conn)->create('finance_sales', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('account_id', 64)->index();
            $table->unsignedBigInteger('vendor_id')->nullable()->index();

            $table->enum('origin', ['recurrente', 'no_recurrente'])->default('no_recurrente')->index();
            $table->enum('periodicity', ['unico', 'mensual', 'anual'])->default('unico')->index();

            $table->date('f_mov')->nullable()->index();
            $table->date('f_cta')->nullable()->index();
            $table->date('invoice_date')->nullable()->index();
            $table->dateTime('paid_at')->nullable()->index();

            $table->decimal('subtotal', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('total', 12, 2)->default(0);

            $table->unsignedBigInteger('statement_id')->nullable()->index();
            $table->unsignedBigInteger('payment_id')->nullable()->index();
            $table->unsignedBigInteger('invoice_request_id')->nullable()->index();

            $table->string('description', 240)->nullable();
            $table->enum('status', ['pending', 'emitido', 'pagado', 'vencido', 'cancelado'])->default('pending')->index();

            $table->json('meta')->nullable();
            $table->timestamps();
        });
    }


    public function down(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        Schema::connection($conn)->dropIfExists('finance_sales');
    }
};
