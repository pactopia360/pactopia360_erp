<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::connection('mysql_admin')->hasTable('modules')) {
      Schema::connection('mysql_admin')->create('modules', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('key')->unique(); // ej: cfdi, rrhh, nomina, reportes
        $t->string('name');
        $t->enum('tier', ['free','pro'])->default('free'); // qué licencia lo incluye por defecto
        $t->boolean('active')->default(true);
        $t->timestamps();
      });
    }

    if (!Schema::connection('mysql_admin')->hasTable('account_modules')) {
      Schema::connection('mysql_admin')->create('account_modules', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('account_id')->index();
        $t->unsignedBigInteger('module_id')->index();
        $t->enum('source', ['plan','manual','promo'])->default('plan');
        $t->boolean('enabled')->default(true);
        $t->timestamps();
        $t->unique(['account_id','module_id']);
      });
    }
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('account_modules');
    Schema::connection('mysql_admin')->dropIfExists('modules');
  }
};
