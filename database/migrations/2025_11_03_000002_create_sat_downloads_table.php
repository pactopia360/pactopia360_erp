<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('sat_downloads', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->uuid('cuenta_id')->index();
            $table->string('rfc', 13)->index();
            $table->enum('tipo', ['emitidos','recibidos'])->index();
            $table->date('date_from')->index();
            $table->date('date_to')->index();

            $table->string('request_id', 191)->nullable()->index();
            $table->string('package_id', 191)->nullable()->index();

            $table->enum('status', ['requested','queued','done','error'])->default('requested')->index();
            $table->string('zip_path')->nullable();
            $table->text('error_message')->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index(['cuenta_id','rfc','tipo','status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_downloads');
    }
};
