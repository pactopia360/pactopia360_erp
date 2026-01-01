<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::connection('mysql_clientes')->hasTable('password_reset_tokens')) {
            return;
        }

        Schema::connection('mysql_clientes')->create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();

            $table->index('created_at', 'ix_prt_created_at');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('password_reset_tokens');
    }
};
