<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_clientes')->table('cfdis', function (Blueprint $table) {
            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'adenda_tipo')) {
                $table->string('adenda_tipo', 80)->nullable();
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'adenda_json')) {
                $table->json('adenda_json')->nullable();
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'adenda_xml')) {
                $table->longText('adenda_xml')->nullable();
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('cfdis', function (Blueprint $table) {
            if (Schema::connection('mysql_clientes')->hasColumn('cfdis', 'adenda_xml')) {
                $table->dropColumn('adenda_xml');
            }

            if (Schema::connection('mysql_clientes')->hasColumn('cfdis', 'adenda_json')) {
                $table->dropColumn('adenda_json');
            }

            if (Schema::connection('mysql_clientes')->hasColumn('cfdis', 'adenda_tipo')) {
                $table->dropColumn('adenda_tipo');
            }
        });
    }
};