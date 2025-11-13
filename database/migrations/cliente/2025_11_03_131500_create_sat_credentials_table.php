<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function getConnection(): string { return 'mysql_clientes'; }
    public function up(): void
    {
        if (! Schema::connection($this->getConnection())->hasTable('sat_credentials')) {
            Schema::connection($this->getConnection())->create('sat_credentials', function (Blueprint $table) {
                $table->id();
                $table->string('cuenta_id', 36)->index();
                $table->string('rfc', 13)->index();
                $table->string('cer_path');
                $table->string('key_path');
                $table->string('key_password');
                $table->json('meta')->nullable();
                $table->timestamps();
                $table->unique(['cuenta_id', 'rfc']);
            });
        }
    }
    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('sat_credentials');
    }
};
