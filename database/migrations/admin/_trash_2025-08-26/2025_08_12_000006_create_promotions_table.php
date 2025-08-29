<?php
// database/migrations/admin/2025_08_12_000006_create_promotions_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::connection('mysql_admin')->hasTable('promotions')) return;

        Schema::connection('mysql_admin')->create('promotions', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('title');
            $t->enum('type',['fixed','percent']); // descuento_fijo/porcentaje
            $t->decimal('value',10,2);
            $t->unsignedBigInteger('plan_id')->nullable();
            $t->date('start_date')->nullable();
            $t->date('end_date')->nullable();
            $t->string('coupon_code',50)->nullable()->unique();
            $t->integer('max_uses')->nullable();
            $t->integer('used_count')->default(0);
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('promotions');
    }
};
