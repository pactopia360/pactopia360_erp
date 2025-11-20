<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esta migración corre en la conexión mysql_admin
     */
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // Cambiamos billing_cycle a VARCHAR(20) para poder guardar: monthly, yearly, etc.
            if (Schema::connection('mysql_admin')->hasColumn('accounts', 'billing_cycle')) {
                $table->string('billing_cycle', 20)->nullable()->change();
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            // ⚠️ Ajusta este tipo al que tenías antes si era otro.
            // Asumo tinyInteger nullable como rollback genérico.
            if (Schema::connection('mysql_admin')->hasColumn('accounts', 'billing_cycle')) {
                $table->tinyInteger('billing_cycle')->nullable()->change();
            }
        });
    }
};
