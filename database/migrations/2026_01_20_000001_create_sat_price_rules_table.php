<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * IMPORTANTE:
     * Esta tabla vive en la BD ADMIN (mysql_admin).
     */
    public function up(): void
    {
        Schema::connection('mysql_admin')->create('sat_price_rules', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('name', 120);
            $table->boolean('active')->default(true)->index();

            // unit: range_per_xml | flat
            $table->string('unit', 24)->default('range_per_xml');

            $table->unsignedInteger('min_xml')->default(0)->index();
            $table->unsignedInteger('max_xml')->nullable()->index();

            // costos
            $table->decimal('price_per_xml', 18, 6)->nullable();
            $table->decimal('flat_price', 18, 2)->nullable();

            $table->string('currency', 8)->default('MXN');

            $table->unsignedInteger('sort')->default(100)->index();

            $table->json('meta')->nullable();

            $table->timestamps();

            // Ayuda a evitar reglas duplicadas (no es único, pero indexa)
            $table->index(['active', 'sort', 'min_xml'], 'spr_active_sort_min_idx');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_admin')->dropIfExists('sat_price_rules');
    }
};
