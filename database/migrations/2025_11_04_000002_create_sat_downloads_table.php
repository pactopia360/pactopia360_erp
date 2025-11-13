<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sat_downloads')) return;

        Schema::create('sat_downloads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('cuenta_id', 36)->index();
            $table->string('rfc', 13)->index();
            $table->enum('tipo', ['emitidos','recibidos'])->index();
            $table->date('date_from')->index();
            $table->date('date_to')->index();
            $table->string('status', 20)->default('pending')->index(); // pending|processing|ready|done|error
            $table->string('request_id', 191)->nullable()->index();
            $table->string('package_id', 191)->nullable();
            $table->string('zip_path', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['cuenta_id','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_downloads');
    }
};
