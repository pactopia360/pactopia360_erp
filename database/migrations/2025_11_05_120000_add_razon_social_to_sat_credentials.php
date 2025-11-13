<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sat_credentials') && !Schema::hasColumn('sat_credentials', 'razon_social')) {
            Schema::table('sat_credentials', function (Blueprint $table) {
                $table->string('razon_social', 190)->nullable()->after('rfc');
                $table->index(['cuenta_id', 'rfc']);
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sat_credentials') && Schema::hasColumn('sat_credentials', 'razon_social')) {
            Schema::table('sat_credentials', function (Blueprint $table) {
                $table->dropColumn('razon_social');
            });
        }
    }
};
