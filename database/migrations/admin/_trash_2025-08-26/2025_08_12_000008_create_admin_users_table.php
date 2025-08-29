<?php

// database/migrations/admin/2025_08_12_000008_create_admin_users_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_admin')->hasTable('admin_users')) return;
    Schema::connection('mysql_admin')->create('admin_users', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->string('name');
      $t->string('email')->unique();
      $t->string('password');
      $t->enum('role',['superadmin','ventas','soporte','dev','contabilidad','admin'])->default('admin');
      $t->boolean('active')->default(true);
      $t->rememberToken();
      $t->timestamps();
    });
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('admin_users');
  }
};
