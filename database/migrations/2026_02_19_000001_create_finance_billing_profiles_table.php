<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (Schema::connection($conn)->hasTable('finance_billing_profiles')) {
            return;
        }

        Schema::connection($conn)->create('finance_billing_profiles', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('account_id', 64)->index();
            $table->string('rfc_receptor', 20)->nullable()->index();
            $table->string('razon_social', 180)->nullable();
            $table->string('email_cfdi', 190)->nullable();

            $table->string('uso_cfdi', 10)->nullable();
            $table->string('regimen_fiscal', 10)->nullable();
            $table->string('cp_fiscal', 10)->nullable();

            $table->string('forma_pago', 10)->nullable();
            $table->string('metodo_pago', 10)->nullable();

            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique(['account_id']);
        });
    }


    public function down(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        Schema::connection($conn)->dropIfExists('finance_billing_profiles');
    }
};
