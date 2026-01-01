<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('sat_downloads', function (Blueprint $table) {
            if (!Schema::hasColumn('sat_downloads', 'download_id')) {
                $table->uuid('download_id')->nullable()->index();
            }
        });
    }

    public function down(): void
    {
        Schema::table('sat_downloads', function (Blueprint $table) {
            if (Schema::hasColumn('sat_downloads', 'download_id')) {
                $table->dropColumn('download_id');
            }
        });
    }

};
