<?php
// database/migrations/admin/2025_08_12_000003_create_plans_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::connection('mysql_admin')->hasTable('plans')) return;

        Schema::connection('mysql_admin')->create('plans', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('name')->unique(); // free, premium
            $t->decimal('price_monthly',10,2)->default(0);
            $t->decimal('price_annual',10,2)->default(0);
            $t->json('included_features')->nullable(); // qué módulos van por plan
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('plans');
    }
};
