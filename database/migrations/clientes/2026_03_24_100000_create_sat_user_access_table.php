<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sat_user_access')) {
            return;
        }

        Schema::connection($this->connection)->create('sat_user_access', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->uuid('cuenta_id')->index();
            $table->uuid('usuario_id')->index();

            $table->boolean('can_access_vault')->default(false)->index();
            $table->boolean('can_upload_metadata')->default(false);
            $table->boolean('can_upload_xml')->default(false);
            $table->boolean('can_export')->default(false);

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['cuenta_id', 'usuario_id'], 'uq_sat_user_access_cuenta_usuario');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sat_user_access');
    }
};