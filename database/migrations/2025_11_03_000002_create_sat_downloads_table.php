<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // IMPORTANTE: si ya existe la tabla (porque la creaste a mano
        // o con otra migración previa), NO la volvemos a crear.
        if (Schema::hasTable('sat_downloads')) {
            return;
        }

        Schema::create('sat_downloads', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->uuid('cuenta_id');
            $table->string('rfc', 13);

            // emitidos / recibidos
            $table->enum('tipo', ['emitidos', 'recibidos']);

            $table->date('date_from');
            $table->date('date_to');

            $table->string('request_id')->nullable();
            $table->string('package_id')->nullable();

            // estados base originales
            $table->enum('status', ['requested', 'queued', 'done', 'error'])
                  ->default('requested');

            $table->string('zip_path')->nullable();
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            // índices básicos
            $table->index('cuenta_id');
            $table->index('rfc');
            $table->index(['cuenta_id', 'rfc']);
            $table->index(['cuenta_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_downloads');
    }
};
