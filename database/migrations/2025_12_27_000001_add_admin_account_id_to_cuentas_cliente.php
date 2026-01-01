<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = config('p360.conn.clients', 'mysql_clientes');

        if (!Schema::connection($conn)->hasTable('cuentas_cliente')) {
            return;
        }

        if (Schema::connection($conn)->hasColumn('cuentas_cliente', 'admin_account_id')) {
            return;
        }

        Schema::connection($conn)->table('cuentas_cliente', function (Blueprint $table) {
            $table->unsignedBigInteger('admin_account_id')
                ->nullable()
                ->index()
                ->after('id');
        });
    }

    public function down(): void
    {
        $conn = config('p360.conn.clients', 'mysql_clientes');

        if (!Schema::connection($conn)->hasTable('cuentas_cliente')) {
            return;
        }

        if (!Schema::connection($conn)->hasColumn('cuentas_cliente', 'admin_account_id')) {
            return;
        }

        Schema::connection($conn)->table('cuentas_cliente', function (Blueprint $table) {
            $table->dropIndex(['admin_account_id']);
            $table->dropColumn('admin_account_id');
        });
    }
};
