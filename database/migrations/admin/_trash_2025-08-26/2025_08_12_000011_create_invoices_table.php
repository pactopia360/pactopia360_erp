<?php
// database/migrations/admin/2025_08_12_000011_create_invoices_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_admin')->hasTable('invoices')) return;
    Schema::connection('mysql_admin')->create('invoices', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('account_id')->index();
      $t->string('number')->unique(); // INV-YYYYMM-XXXX
      $t->date('issue_date');
      $t->date('due_date');
      $t->decimal('subtotal',10,2)->default(0);
      $t->decimal('discount',10,2)->default(0);
      $t->decimal('tax',10,2)->default(0);
      $t->decimal('total',10,2)->default(0);
      $t->enum('status',['pending','paid','canceled','failed','refunded'])->default('pending');
      $t->json('lines')->nullable(); // [{concepto,cantidad,precio}]
      $t->timestamps();
      $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
    });
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('invoices');
  }
};

// database/migrations/admin/2025_08_12_000012_create_payments_table.php
return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_admin')->hasTable('payments')) return;
    Schema::connection('mysql_admin')->create('payments', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('account_id')->index();
      $t->unsignedBigInteger('invoice_id')->nullable()->index();
      $t->decimal('amount',10,2);
      $t->string('method')->nullable(); // card, transfer, oxxo, etc.
      $t->enum('status',['completed','pending','failed','refunded'])->default('pending');
      $t->string('provider')->nullable(); // stripe, conekta
      $t->string('provider_txn_id')->nullable();
      $t->json('meta')->nullable();
      $t->timestamp('paid_at')->nullable();
      $t->timestamps();

      $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
      $t->foreign('invoice_id')->references('id')->on('invoices')->nullOnDelete();
    });
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('payments');
  }
};
