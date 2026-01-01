<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->connection;

        if (Schema::connection($conn)->hasTable('sat_vault_files')) {
            return;
        }

        Schema::connection($conn)->create('sat_vault_files', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->char('cuenta_id', 36)->index();

            $table->string('disk', 50)->default('private');
            $table->string('path', 500);
            $table->string('filename', 255)->nullable();
            $table->unsignedBigInteger('bytes')->default(0);

            $table->string('rfc', 13)->nullable()->index();
            $table->string('source', 50)->nullable();
            $table->string('source_id', 64)->nullable()->index();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sat_vault_files');
    }
};
