<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sat_credentials')) {
            Schema::table('sat_credentials', function (Blueprint $t) {
                if (!Schema::hasColumn('sat_credentials', 'razon_social')) {
                    $t->string('razon_social', 190)->nullable()->after('rfc');
                }
            });
        }

        if (Schema::hasTable('sat_downloads')) {
            Schema::table('sat_downloads', function (Blueprint $t) {
                if (!Schema::hasColumn('sat_downloads', 'auto')) {
                    $t->boolean('auto')->default(false)->after('status');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sat_credentials')) {
            Schema::table('sat_credentials', function (Blueprint $t) {
                if (Schema::hasColumn('sat_credentials', 'razon_social')) {
                    $t->dropColumn('razon_social');
                }
            });
        }
        if (Schema::hasTable('sat_downloads')) {
            Schema::table('sat_downloads', function (Blueprint $t) {
                if (Schema::hasColumn('sat_downloads', 'auto')) {
                    $t->dropColumn('auto');
                }
            });
        }
    }
};
