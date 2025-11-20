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
            // billing_status: active, trial, grace, overdue, suspended, cancelled, demo, etc.
            if (!Schema::connection('mysql_admin')->hasColumn('accounts', 'billing_status')) {
                $table->string('billing_status', 30)
                    ->nullable()
                    ->after('billing_cycle')
                    ->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            if (Schema::connection('mysql_admin')->hasColumn('accounts', 'billing_status')) {
                $table->dropColumn('billing_status');
            }
        });
    }
};
