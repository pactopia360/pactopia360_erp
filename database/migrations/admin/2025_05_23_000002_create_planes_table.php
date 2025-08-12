<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/2025_05_23_000002_create_planes_table.php
return new class extends Migration {
    public function up(): void {
        Schema::create('planes', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 40)->unique(); // Free / Premium
            $t->decimal('precio_mensual', 10, 2)->default(0);
            $t->decimal('precio_anual', 10, 2)->default(0);
            $t->json('features')->nullable();   // {"espacio_gb":1, "hits_incluidos":20, ...}
            $t->boolean('activo')->default(true);
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('planes'); }
};
