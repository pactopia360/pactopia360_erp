<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Si no existe la tabla accounts en esta conexión, salimos
        if (! Schema::hasTable('accounts')) {
            return;
        }

        // Si ya existe billing_status, no hacemos nada
        if (Schema::hasColumn('accounts', 'billing_status')) {
            return;
        }

        // Vemos si existe billing_cycle (para usar AFTER) o no
        $hasBillingCycle = Schema::hasColumn('accounts', 'billing_cycle');

        Schema::table('accounts', function (Blueprint $table) use ($hasBillingCycle) {
            if ($hasBillingCycle) {
                // En DB donde SÍ existe billing_cycle
                $table->string('billing_status', 30)
                    ->nullable()
                    ->after('billing_cycle');
            } else {
                // En DB donde NO existe billing_cycle, la agregamos al final
                $table->string('billing_status', 30)
                    ->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('accounts')) {
            return;
        }

        if (! Schema::hasColumn('accounts', 'billing_status')) {
            return;
        }

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('billing_status');
        });
    }
};
