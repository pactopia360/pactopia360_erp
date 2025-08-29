<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('planes')) {
            Schema::create('planes', function (Blueprint $t) {
                $t->id();
                $t->string('clave')->unique();
                $t->string('nombre');
                $t->decimal('precio_mensual',10,2)->default(0);
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }
        if (!Schema::hasTable('clientes')) {
            Schema::create('clientes', function (Blueprint $t) {
                $t->id();
                $t->string('razon_social')->nullable();
                $t->string('nombre_comercial')->nullable();
                $t->string('rfc',13)->nullable();
                $t->unsignedBigInteger('plan_id')->nullable();
                $t->string('plan')->nullable(); // fallback
                $t->boolean('activo')->default(true);
                $t->timestamps();
                $t->index('plan_id');
                $t->index('plan');
            });
        }
    }
    public function down(): void {}
};
