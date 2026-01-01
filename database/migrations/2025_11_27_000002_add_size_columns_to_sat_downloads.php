<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
      Schema::connection('mysql_clientes')->table('sat_downloads', function (Blueprint $table) {
        $schema = Schema::connection('mysql_clientes');

        if (!$schema->hasColumn('sat_downloads', 'size_mb')) {
          $table->double('size_mb', 15, 8)->unsigned()->default(0)->after('size_bytes');
        }

        if (!$schema->hasColumn('sat_downloads', 'size_gb')) {
          $table->double('size_gb', 15, 12)->unsigned()->default(0)->after('size_mb');
        }
      });
    }

    public function down(): void
    {
      Schema::connection('mysql_clientes')->table('sat_downloads', function (Blueprint $table) {
        $schema = Schema::connection('mysql_clientes');

        if ($schema->hasColumn('sat_downloads', 'size_mb')) {
          $table->dropColumn('size_mb');
        }

        if ($schema->hasColumn('sat_downloads', 'size_gb')) {
          $table->dropColumn('size_gb');
        }
      });
    }
};
