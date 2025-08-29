<?php
// database/migrations/admin/2025_08_12_000001_create_accounts_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::connection('mysql_admin')->hasTable('accounts')) {
            return;
        }

        Schema::connection('mysql_admin')->create('accounts', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->string('rfc', 13)->unique(); // RFC padre (dato maestro)
            $t->string('razon_social', 255)->nullable();
            $t->string('correo_contacto', 255)->index();
            $t->string('telefono', 20)->nullable();
            $t->enum('plan', ['free', 'premium'])->default('free'); // estado inicial free
            $t->enum('billing_cycle', ['monthly', 'annual'])->nullable(); // solo aplica premium
            $t->date('next_invoice_date')->nullable();
            $t->boolean('is_blocked')->default(false);
            $t->json('features')->nullable(); // límites, espacio, hits iniciales, etc.
            $t->timestamps();
            $t->softDeletes();
        });
    }

    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('accounts');
    }
};
