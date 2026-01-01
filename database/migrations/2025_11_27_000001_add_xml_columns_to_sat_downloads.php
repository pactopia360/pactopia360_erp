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

            if (!$schema->hasColumn('sat_downloads', 'xml_count')) {
                $table->unsignedInteger('xml_count')
                    ->default(0)
                    ->after('costo');
            }

            if (!$schema->hasColumn('sat_downloads', 'total_xml')) {
                $table->unsignedInteger('total_xml')
                    ->default(0)
                    ->after('xml_count');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('sat_downloads', function (Blueprint $table) {
            $schema = Schema::connection('mysql_clientes');

            if ($schema->hasColumn('sat_downloads', 'xml_count')) {
                $table->dropColumn('xml_count');
            }

            if ($schema->hasColumn('sat_downloads', 'total_xml')) {
                $table->dropColumn('total_xml');
            }
        });
    }
};
