<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_clientes')->hasTable('accounts')) return;

    Schema::connection('mysql_clientes')->create('accounts', function (Blueprint $t) {
      $t->bigIncrements('id');             // igual que admin
      $t->string('rfc',13)->unique();
      $t->string('razon_social')->nullable();
      $t->string('correo_contacto')->index();
      $t->string('telefono')->nullable();
      $t->enum('plan',['free','premium'])->default('free');
      $t->enum('billing_cycle',['monthly','annual'])->nullable();
      $t->date('next_invoice_date')->nullable();
      $t->boolean('is_blocked')->default(false);
      $t->string('user_code',28)->unique(); // código único visible al cliente
      $t->timestamps();
      $t->softDeletes();
    });
  }
  public function down(): void {
    Schema::connection('mysql_clientes')->dropIfExists('accounts');
  }
};
