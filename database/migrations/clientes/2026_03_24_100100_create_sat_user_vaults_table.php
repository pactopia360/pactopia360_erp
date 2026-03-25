<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sat_user_vaults')) {
            return;
        }

        Schema::connection($this->connection)->create('sat_user_vaults', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->uuid('cuenta_id')->index();
            $table->uuid('usuario_id')->index();

            $table->string('rfc', 13)->index();
            $table->string('alias', 150)->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->unique(['cuenta_id', 'usuario_id', 'rfc'], 'uq_sat_user_vaults_cuenta_usuario_rfc');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sat_user_vaults');
    }
};