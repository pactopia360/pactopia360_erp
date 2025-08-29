<?php

// database/migrations/admin/2025_08_12_000007_create_customers_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_admin')->hasTable('customers')) return;
    Schema::connection('mysql_admin')->create('customers', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('account_id')->index();
      $t->string('rfc',13)->index();
      $t->string('business_name')->nullable();
      $t->string('tradename')->nullable();
      $t->json('address')->nullable();
      $t->boolean('active')->default(true);
      $t->timestamps();
      $t->softDeletes();
      $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
    });
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('customers');
  }
};
