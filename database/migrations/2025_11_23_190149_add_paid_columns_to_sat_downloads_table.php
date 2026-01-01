<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Cuando corres: php artisan migrate --database=mysql_clientes
        // Laravel usa esa conexión para todas estas operaciones.

        if (! Schema::hasTable('sat_downloads')) {
            return;
        }

        Schema::table('sat_downloads', function (Blueprint $table) {
            if (! Schema::hasColumn('sat_downloads', 'paid_at')) {
                $table->timestamp('paid_at')
                      ->nullable()
                      ->after('status');
            }

            if (! Schema::hasColumn('sat_downloads', 'stripe_session_id')) {
                $table->string('stripe_session_id', 191)
                      ->nullable()
                      ->after('paid_at');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('sat_downloads')) {
            return;
        }

        Schema::table('sat_downloads', function (Blueprint $table) {
            if (Schema::hasColumn('sat_downloads', 'paid_at')) {
                $table->dropColumn('paid_at');
            }

            if (Schema::hasColumn('sat_downloads', 'stripe_session_id')) {
                $table->dropColumn('stripe_session_id');
            }
        });
    }
};
