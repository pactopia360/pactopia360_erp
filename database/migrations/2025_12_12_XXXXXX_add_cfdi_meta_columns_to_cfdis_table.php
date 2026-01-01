<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysql_clientes')->table('cfdis', function (Blueprint $table) {
            if (!Schema::connection('mysql_clientes')->hasColumn('cfdis','tipo')) {
                $table->string('tipo', 20)->nullable()->index(); // emitidos/recibidos
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cfdis','rfc_emisor')) {
                $table->string('rfc_emisor', 13)->nullable()->index();
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cfdis','rfc_receptor')) {
                $table->string('rfc_receptor', 13)->nullable()->index();
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cfdis','razon_emisor')) {
                $table->string('razon_emisor', 255)->nullable();
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cfdis','razon_receptor')) {
                $table->string('razon_receptor', 255)->nullable();
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cfdis','subtotal')) {
                $table->decimal('subtotal', 14, 2)->default(0);
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cfdis','iva')) {
                $table->decimal('iva', 14, 2)->default(0);
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('cfdis', function (Blueprint $table) {
            foreach (['tipo','rfc_emisor','rfc_receptor','razon_emisor','razon_receptor','subtotal','iva'] as $col) {
                if (Schema::connection('mysql_clientes')->hasColumn('cfdis',$col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
