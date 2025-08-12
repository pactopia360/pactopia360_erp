<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('usuario_administrativos', function (Blueprint $table) {
            $table->id();
            $table->string('nombre', 120);
            $table->string('email', 190)->unique();
            $table->string('password', 255);
            $table->string('rol', 50)->default('superadmin'); // superadmin|ventas|soporte|conta
            $table->boolean('activo')->default(true);
            $table->rememberToken();
            $table->timestamps();
        });
    }


    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('usuario_administrativos');
    }
};
