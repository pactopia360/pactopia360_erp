<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_clientes')->hasTable('companies')) return;

    Schema::connection('mysql_clientes')->create('companies', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('account_id')->index();
      $t->string('rfc',13)->index();
      $t->string('razon_social')->nullable();
      $t->json('fiscal_data')->nullable(); // domicilio, regimen, etc.
      $t->boolean('active')->default(true);
      $t->timestamps();
    });
  }
  public function down(): void {
    Schema::connection('mysql_clientes')->dropIfExists('companies');
  }
};
