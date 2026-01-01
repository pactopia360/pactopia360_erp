<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!Schema::connection($conn)->hasTable('account_recipients')) {
            return;
        }

        Schema::connection($conn)->table('account_recipients', function (Blueprint $table) use ($conn) {

            if (!Schema::connection($conn)->hasColumn('account_recipients', 'is_active')) {
                $table->tinyInteger('is_active')->default(1)->after('kind');
            }

            if (!Schema::connection($conn)->hasColumn('account_recipients', 'is_primary')) {
                $table->tinyInteger('is_primary')->default(0)->after('is_active');
            }
        });
    }

    public function down(): void
    {
        $conn = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (!Schema::connection($conn)->hasTable('account_recipients')) {
            return;
        }

        Schema::connection($conn)->table('account_recipients', function (Blueprint $table) use ($conn) {

            if (Schema::connection($conn)->hasColumn('account_recipients', 'is_primary')) {
                $table->dropColumn('is_primary');
            }

            if (Schema::connection($conn)->hasColumn('account_recipients', 'is_active')) {
                $table->dropColumn('is_active');
            }
        });
    }
};
