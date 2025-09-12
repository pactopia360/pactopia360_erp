<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('crm_contactos')) {
            Schema::create('crm_contactos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('empresa_slug', 60)->index();
                $table->string('nombre', 120);
                $table->string('email', 160)->nullable()->unique();
                $table->string('telefono', 40)->nullable();
                $table->string('puesto', 120)->nullable();
                $table->text('notas')->nullable();
                $table->boolean('activo')->default(true)->index();
                $table->json('tags')->nullable();
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_contactos');
    }
};
