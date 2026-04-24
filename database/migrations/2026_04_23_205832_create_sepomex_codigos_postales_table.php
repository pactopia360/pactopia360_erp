<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection('mysql_clientes')->hasTable('sepomex_codigos_postales')) {
            return;
        }

        Schema::connection('mysql_clientes')->create('sepomex_codigos_postales', function (Blueprint $table) {
            $table->id();

            $table->string('codigo_postal', 5)->index();
            $table->string('estado', 120)->index();
            $table->string('municipio', 120)->index();
            $table->string('ciudad', 120)->nullable();
            $table->string('colonia', 180)->index();
            $table->string('tipo_asentamiento', 80)->nullable();

            $table->string('estado_clave', 10)->nullable()->index();
            $table->string('municipio_clave', 10)->nullable()->index();
            $table->string('zona', 40)->nullable();

            $table->boolean('activo')->default(true)->index();
            $table->json('extras')->nullable();

            $table->timestamps();

            $table->unique(
                ['codigo_postal', 'estado', 'municipio', 'colonia'],
                'sepomex_cp_estado_municipio_colonia_unique'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('sepomex_codigos_postales');
    }
};