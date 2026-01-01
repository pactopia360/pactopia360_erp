<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysql_admin')->table('accounts', function (Blueprint $table) {
            if (!Schema::connection('mysql_admin')->hasColumn('accounts', 'razon_social')) {
                $table->string('razon_social', 255)->nullable()->after('rfc');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_admin')->table('accounts', function (Blueprint $table) {
            if (Schema::connection('mysql_admin')->hasColumn('accounts', 'razon_social')) {
                $table->dropColumn('razon_social');
            }
        });
    }
};
