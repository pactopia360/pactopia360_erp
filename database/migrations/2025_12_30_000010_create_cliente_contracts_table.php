<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection($this->conn)->hasTable('cliente_contracts')) {
            return;
        }

        Schema::connection($this->conn)->create('cliente_contracts', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('account_id')->index();

            $table->string('template_key', 64)->default('timbrado_general')->index();
            $table->unsignedInteger('template_version')->default(1);

            $table->string('status', 20)->default('pending')->index();

            $table->longText('snapshot_html')->nullable();
            $table->string('content_hash', 64)->nullable()->index();

            $table->timestamp('signed_at')->nullable()->index();
            $table->string('signed_by_user_id', 36)->nullable()->index();
            $table->string('signed_ip', 64)->nullable();
            $table->string('signed_user_agent', 255)->nullable();

            $table->string('pdf_path', 255)->nullable();

            $table->timestamps();

            $table->unique(['account_id', 'template_key', 'template_version'], 'uq_contract_account_template');
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('cliente_contracts');
    }
};
