<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sat_downloads', function (Blueprint $t) {
            if (!Schema::hasColumn('sat_downloads','auto')) {
                $t->boolean('auto')->default(false)->index()->after('status');
            }
            if (!Schema::hasColumn('sat_downloads','last_checked_at')) {
                $t->timestamp('last_checked_at')->nullable()->after('auto');
            }
            if (!Schema::hasColumn('sat_downloads','attempts')) {
                $t->unsignedSmallInteger('attempts')->default(0)->after('last_checked_at');
            }
            if (!Schema::hasColumn('sat_downloads','error_message')) {
                $t->string('error_message', 500)->nullable()->after('attempts');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sat_downloads', function (Blueprint $t) {
            if (Schema::hasColumn('sat_downloads','auto')) $t->dropColumn('auto');
            if (Schema::hasColumn('sat_downloads','last_checked_at')) $t->dropColumn('last_checked_at');
            if (Schema::hasColumn('sat_downloads','attempts')) $t->dropColumn('attempts');
            if (Schema::hasColumn('sat_downloads','error_message')) $t->dropColumn('error_message');
        });
    }
};
