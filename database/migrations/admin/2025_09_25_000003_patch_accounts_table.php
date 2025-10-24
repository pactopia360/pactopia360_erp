<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('accounts')) {
            // Si por alguna razón no existe, no intentamos crearla aquí, solo salimos.
            return;
        }

        Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('accounts', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('plan');
            }
            if (!Schema::connection($this->connection)->hasColumn('accounts', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            }
            if (!Schema::connection($this->connection)->hasColumn('accounts', 'is_blocked')) {
                $table->boolean('is_blocked')->default(false)->after('phone_verified_at');
            }
            if (!Schema::connection($this->connection)->hasColumn('accounts', 'meta')) {
                $table->json('meta')->nullable()->after('is_blocked');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable('accounts')) {
            return;
        }
        Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('accounts', 'meta')) {
                $table->dropColumn('meta');
            }
            if (Schema::connection($this->connection)->hasColumn('accounts', 'is_blocked')) {
                $table->dropColumn('is_blocked');
            }
            if (Schema::connection($this->connection)->hasColumn('accounts', 'phone_verified_at')) {
                $table->dropColumn('phone_verified_at');
            }
            if (Schema::connection($this->connection)->hasColumn('accounts', 'email_verified_at')) {
                $table->dropColumn('email_verified_at');
            }
        });
    }
};
