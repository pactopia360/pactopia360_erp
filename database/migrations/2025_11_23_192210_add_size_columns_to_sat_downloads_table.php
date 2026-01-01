<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('sat_downloads', function (Blueprint $table) {
            if (!Schema::hasColumn('sat_downloads', 'size_bytes')) {
                $table->unsignedBigInteger('size_bytes')
                      ->nullable()
                      ->after('zip_path');
            }

            if (!Schema::hasColumn('sat_downloads', 'size_gb')) {
                $table->decimal('size_gb', 12, 8)
                      ->nullable()
                      ->after('size_bytes');
            }
        });
    }

    public function down(): void
    {
        Schema::table('sat_downloads', function (Blueprint $table) {
            if (Schema::hasColumn('sat_downloads', 'size_gb')) {
                $table->dropColumn('size_gb');
            }
            if (Schema::hasColumn('sat_downloads', 'size_bytes')) {
                $table->dropColumn('size_bytes');
            }
        });
    }
};
