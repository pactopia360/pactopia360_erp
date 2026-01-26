<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * ✅ ADMIN SOT
     * IMPORTANT: NO tipar $connection (sin ": string"), para evitar:
     * "Type of ...::$connection must not be defined"
     */
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        Schema::connection($this->connection)->create('account_recipients', function (Blueprint $table) {
            $table->bigIncrements('id');

            // accounts.id (en tu SOT suele ser RFC/string)
            $table->string('account_id', 64)->index();

            // receptor
            $table->string('email', 190)->index();
            $table->string('name', 190)->nullable();

            /**
             * tipo de receptor:
             * - statement: estados de cuenta
             * - invoice: facturas
             * - general: comunicaciones generales
             */
            $table->string('kind', 30)->default('statement')->index();

            // flags
            $table->unsignedTinyInteger('is_primary')->default(0)->index(); // ✅ requerido por tu controller
            $table->unsignedTinyInteger('is_active')->default(1)->index();

            // opcional
            $table->json('meta')->nullable();

            $table->timestamps();

            // Evita duplicados por cuenta/correo/tipo
            $table->unique(['account_id', 'email', 'kind'], 'uq_account_recipients_acc_email_kind');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('account_recipients');
    }
};
