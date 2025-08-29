<?php

// database/migrations/admin/2025_08_12_000005_create_plan_module_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_admin')->hasTable('plan_module')) return;
    Schema::connection('mysql_admin')->create('plan_module', function (Blueprint $t) {
      $t->unsignedBigInteger('plan_id');
      $t->unsignedBigInteger('module_id');
      $t->primary(['plan_id','module_id']);
      $t->foreign('plan_id')->references('id')->on('plans')->cascadeOnDelete();
      $t->foreign('module_id')->references('id')->on('modules')->cascadeOnDelete();
    });
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('plan_module');
  }
};