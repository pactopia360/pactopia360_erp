<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::connection('mysql_admin')->hasTable('payments')) {
      Schema::connection('mysql_admin')->create('payments', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('account_id')->index();
        $t->enum('concept',['subscription','hit_pack','addon'])->default('subscription');
        $t->decimal('amount',10,2);
        $t->string('currency',3)->default('MXN');
        $t->enum('status',['pending','paid','failed','canceled'])->default('pending')->index();
        $t->string('method')->nullable();   // stripe|conekta|transferencia
        $t->string('reference')->nullable();
        $t->json('meta')->nullable();      // {period:'2025-08', cycle:'monthly', pack_id:...}
        $t->date('due_date')->nullable();  // fecha de vencimiento (día 1)
        $t->timestamps();
      });
    }

    if (!Schema::connection('mysql_admin')->hasTable('invoices')) {
      Schema::connection('mysql_admin')->create('invoices', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('payment_id')->index();
        $t->string('uuid')->nullable();    // CFDI UUID si aplica
        $t->string('folio')->nullable();
        $t->dateTime('issued_at')->nullable();
        $t->json('xml')->nullable();
        $t->json('pdf')->nullable();
        $t->timestamps();
      });
    }
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('invoices');
    Schema::connection('mysql_admin')->dropIfExists('payments');
  }
};
