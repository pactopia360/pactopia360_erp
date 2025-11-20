<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esta migración corre en la conexión mysql_admin
     */
    protected $connection = 'mysql_admin'; // SIN tipo

    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Cambiamos plan a VARCHAR(50) para poder guardar: free, pro, etc.
            $table->string('plan', 50)->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Ajusta esto al tipo original si era diferente
            $table->tinyInteger('plan')->nullable()->change();
        });
    }
};
