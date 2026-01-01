<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysql_admin')->table('payments', function (Blueprint $t) {
            if (!Schema::connection('mysql_admin')->hasColumn('payments', 'period')) {
                $t->string('period', 7)->nullable()->index()->comment('YYYY-MM');
            }
            if (!Schema::connection('mysql_admin')->hasColumn('payments', 'method')) {
                $t->string('method', 50)->nullable()->comment('card|transfer|cash|stripe');
            }
            if (!Schema::connection('mysql_admin')->hasColumn('payments', 'provider')) {
                $t->string('provider', 50)->nullable()->comment('stripe|manual');
            }
            if (!Schema::connection('mysql_admin')->hasColumn('payments', 'concept')) {
                $t->string('concept', 191)->nullable();
            }
            if (!Schema::connection('mysql_admin')->hasColumn('payments', 'reference')) {
                $t->string('reference', 191)->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_admin')->table('payments', function (Blueprint $t) {
            // Down opcional (no recomendado en prod)
        });
    }
};
