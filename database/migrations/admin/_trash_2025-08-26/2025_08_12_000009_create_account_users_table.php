<?php

// database/migrations/admin/2025_08_12_000009_create_account_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_admin')->hasTable('account_users')) return;
    Schema::connection('mysql_admin')->create('account_users', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('account_id')->index();
      $t->string('email')->index();
      $t->string('password');
      $t->string('name')->nullable();
      $t->string('rfc',13)->nullable(); // puede repetir con padre, pero el padre vive en accounts
      $t->string('user_code')->unique(); // código único visible
      $t->enum('kind',['owner','member'])->default('member'); // padre/hijo
      $t->boolean('active')->default(true);
      $t->timestamps();
      $t->softDeletes();
      $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
    });
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('account_users');
  }
};
