<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_admin')->table('accounts', function (Blueprint $t) {
            if (!Schema::connection('mysql_admin')->hasColumn('accounts', 'meta')) {
                $t->json('meta')->nullable()->comment('JSON meta: billing, modules, etc.');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_admin')->table('accounts', function (Blueprint $t) {
            if (Schema::connection('mysql_admin')->hasColumn('accounts', 'meta')) {
                $t->dropColumn('meta');
            }
        });
    }
};
