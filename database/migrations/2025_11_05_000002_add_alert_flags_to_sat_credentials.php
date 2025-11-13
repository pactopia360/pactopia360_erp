<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sat_credentials', function (Blueprint $t) {
            if (!Schema::hasColumn('sat_credentials','auto_download')) {
                $t->boolean('auto_download')->default(true)->index()->after('validated_at');
            }
            if (!Schema::hasColumn('sat_credentials','alert_canceled')) {
                $t->boolean('alert_canceled')->default(true)->after('auto_download');
            }
            if (!Schema::hasColumn('sat_credentials','alert_email')) {
                $t->string('alert_email', 190)->nullable()->after('alert_canceled');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sat_credentials', function (Blueprint $t) {
            if (Schema::hasColumn('sat_credentials','auto_download')) $t->dropColumn('auto_download');
            if (Schema::hasColumn('sat_credentials','alert_canceled')) $t->dropColumn('alert_canceled');
            if (Schema::hasColumn('sat_credentials','alert_email')) $t->dropColumn('alert_email');
        });
    }
};
