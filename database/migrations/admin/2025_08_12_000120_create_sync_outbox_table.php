<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('sync_outbox', function (Blueprint $table) {
            $table->id();
            $table->string('entidad', 100);
            $table->string('entidad_id', 36);
            $table->string('accion', 50);
            $table->json('payload_json');
            $table->bigInteger('version')->default(1);
            $table->integer('retries')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();
        });
    }
    public function down(): void {
        Schema::dropIfExists('sync_outbox');
    }
};
