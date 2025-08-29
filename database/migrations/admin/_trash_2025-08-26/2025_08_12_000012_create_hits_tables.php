<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::connection('mysql_admin')->hasTable('hit_packs')) {
      Schema::connection('mysql_admin')->create('hit_packs', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name');           // p.ej.: Pack 20, Pack 100
        $t->integer('hits');          // cantidad
        $t->decimal('price',10,2);    // MXN
        $t->boolean('active')->default(true);
        $t->timestamps();
      });
    }

    if (!Schema::connection('mysql_admin')->hasTable('hit_ledger')) {
      Schema::connection('mysql_admin')->create('hit_ledger', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('account_id')->index();
        $t->enum('type',['purchase','consume','adjust'])->index();
        $t->integer('quantity');                 // positivo compra/ajuste+, negativo consumo/ajuste-
        $t->string('ref')->nullable();           // referencia pago / UUID CFDI / nota
        $t->unsignedBigInteger('payment_id')->nullable()->index(); // link a payments
        $t->timestamps();
      });
    }

    if (!Schema::connection('mysql_admin')->hasTable('account_quota')) {
      Schema::connection('mysql_admin')->create('account_quota', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('account_id')->unique();
        $t->integer('storage_gb')->default(1);   // Free: 1GB, Pro: 50GB
        $t->integer('hits_balance')->default(0); // saldo actual (cache del ledger)
        $t->timestamps();
      });
    }
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('account_quota');
    Schema::connection('mysql_admin')->dropIfExists('hit_ledger');
    Schema::connection('mysql_admin')->dropIfExists('hit_packs');
  }
};
