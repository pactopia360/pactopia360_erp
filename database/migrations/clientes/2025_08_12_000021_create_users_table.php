<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_clientes')->hasTable('users')) return;

    Schema::connection('mysql_clientes')->create('users', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('account_id')->index();
      $t->string('name');
      $t->string('email')->unique();
      $t->string('password');
      $t->enum('role',['owner','member'])->default('member'); // dueño=padre, hijos=member
      $t->boolean('email_verified')->default(false);
      $t->string('avatar_path')->nullable();
      $t->rememberToken();
      $t->timestamps();
    });
  }
  public function down(): void {
    Schema::connection('mysql_clientes')->dropIfExists('users');
  }
};
