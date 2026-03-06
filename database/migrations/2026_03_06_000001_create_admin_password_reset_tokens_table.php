<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $connection = (string) (config('auth.passwords.admins.connection') ?: 'mysql_admin');
        $table      = (string) (config('auth.passwords.admins.table') ?: 'password_reset_tokens');

        if (Schema::connection($connection)->hasTable($table)) {
            return;
        }

        Schema::connection($connection)->create($table, function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        $connection = (string) (config('auth.passwords.admins.connection') ?: 'mysql_admin');
        $table      = (string) (config('auth.passwords.admins.table') ?: 'password_reset_tokens');

        Schema::connection($connection)->dropIfExists($table);
    }
};