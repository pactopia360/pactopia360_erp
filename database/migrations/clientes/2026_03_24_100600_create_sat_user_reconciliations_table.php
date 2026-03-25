<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('sat_user_reconciliations')) {
            return;
        }

        Schema::connection($this->connection)->create('sat_user_reconciliations', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->uuid('cuenta_id')->index();
            $table->uuid('usuario_id')->index();
            $table->string('rfc_owner', 13)->index();

            $table->unsignedBigInteger('metadata_item_id')->nullable()->index();
            $table->unsignedBigInteger('cfdi_id')->nullable()->index();

            $table->string('uuid', 64)->nullable()->index();
            $table->string('status', 30)->default('pending')->index();
            // pending|match_exacto|match_con_diferencias|solo_metadata|solo_xml|cancelado|no_relacionado

            $table->json('differences_json')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['cuenta_id', 'usuario_id', 'rfc_owner', 'status'], 'idx_sat_user_recon_scope_status');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sat_user_reconciliations');
    }
};