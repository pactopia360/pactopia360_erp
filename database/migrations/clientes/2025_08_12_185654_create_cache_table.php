<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::connection('mysql_clientes')->hasTable('cache')) {
            Schema::connection('mysql_clientes')->create('cache', function (Blueprint $table) {
                $table->string('key')->primary();
                $table->mediumText('value');
                $table->integer('expiration');
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('cache');
    }
};
