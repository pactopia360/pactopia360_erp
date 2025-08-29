<?php
// database/migrations/admin/2025_08_12_000004_create_modules_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_admin')->hasTable('modules')) return;
    Schema::connection('mysql_admin')->create('modules', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->string('key')->unique();     // 'cfdi', 'rrhh', 'nomina', 'reportes', etc.
      $t->string('name');
      $t->enum('tier', ['free','pro'])->default('free'); // qué plan lo incluye
      $t->boolean('active')->default(true);
      $t->json('meta')->nullable();    // límites, notas, etc.
      $t->timestamps();
    });
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('modules');
  }
};