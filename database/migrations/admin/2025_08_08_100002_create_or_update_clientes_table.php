<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('clientes')) {
            Schema::create('clientes', function (Blueprint $table) {
                $table->id();
                $table->string('razon_social', 200);
                $table->string('nombre_comercial', 200)->nullable();
                $table->string('rfc', 20)->nullable()->index();
                $table->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete()->index();
                $table->string('plan', 50)->nullable(); // compat si no usan FK
                $table->boolean('activo')->default(true)->index();
                $table->timestamps();
            });
        } else {
            Schema::table('clientes', function (Blueprint $table) {
                if (!Schema::hasColumn('clientes','razon_social')) $table->string('razon_social',200)->nullable();
                if (!Schema::hasColumn('clientes','nombre_comercial')) $table->string('nombre_comercial',200)->nullable();
                if (!Schema::hasColumn('clientes','rfc')) $table->string('rfc',20)->nullable()->index();
                if (!Schema::hasColumn('clientes','plan_id')) $table->foreignId('plan_id')->nullable()->after('rfc')->index();
                if (!Schema::hasColumn('clientes','plan')) $table->string('plan',50)->nullable();
                if (!Schema::hasColumn('clientes','activo')) $table->boolean('activo')->default(true)->index();
                if (!Schema::hasColumn('clientes','created_at')) $table->timestamps();
            });
        }
    }
    public function down(): void {
        Schema::dropIfExists('clientes');
    }
};
