<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function getConnection(): string { return 'mysql_clientes'; }

    public function up(): void
    {
        if (! Schema::connection($this->getConnection())->hasTable('sat_downloads')) {
            Schema::connection($this->getConnection())->create('sat_downloads', function (Blueprint $table) {
                $table->id();
                $table->string('cuenta_id', 36)->index();   // UUID/ULID
                $table->string('rfc', 13)->index();
                $table->date('date_from');
                $table->date('date_to');
                $table->enum('tipo', ['emitidos','recibidos'])->default('recibidos')->index();
                $table->string('request_id')->nullable();
                $table->string('package_id')->nullable();
                $table->string('zip_path')->nullable();
                $table->enum('status', ['pending','requested','ready','downloading','done','error'])->default('pending')->index();
                $table->text('error_message')->nullable();
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->index(['cuenta_id','rfc','date_from','date_to']);
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('sat_downloads');
    }
};
