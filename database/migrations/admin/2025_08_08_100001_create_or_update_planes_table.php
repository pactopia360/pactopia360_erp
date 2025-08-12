<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('planes')) {
            Schema::create('planes', function (Blueprint $table) {
                $table->id();
                $table->string('clave', 50)->unique();
                $table->string('nombre', 150);
                $table->text('descripcion')->nullable();
                $table->decimal('precio_mensual', 12, 2)->default(0);
                $table->boolean('activo')->default(true)->index();
                $table->timestamps();
            });
        } else {
            Schema::table('planes', function (Blueprint $table) {
                if (!Schema::hasColumn('planes','clave')) $table->string('clave',50)->nullable()->after('id');
                if (!Schema::hasColumn('planes','nombre')) $table->string('nombre',150)->nullable();
                if (!Schema::hasColumn('planes','descripcion')) $table->text('descripcion')->nullable();
                if (!Schema::hasColumn('planes','precio_mensual')) $table->decimal('precio_mensual',12,2)->default(0);
                if (!Schema::hasColumn('planes','activo')) $table->boolean('activo')->default(true)->index();
                if (!Schema::hasColumn('planes','created_at')) $table->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('planes');
    }
};
