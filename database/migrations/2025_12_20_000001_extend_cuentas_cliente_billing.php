<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysql_clientes')->table('cuentas_cliente', function (Blueprint $t) {

            // Identidad fiscal / comercial
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'nombre_comercial')) {
                $t->string('nombre_comercial', 255)->nullable()->after('razon_social');
            }

            // Dirección fiscal
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'pais')) {
                $t->string('pais', 80)->nullable()->after('telefono');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'calle')) {
                $t->string('calle', 255)->nullable()->after('pais');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'no_ext')) {
                $t->string('no_ext', 50)->nullable()->after('calle');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'no_int')) {
                $t->string('no_int', 50)->nullable()->after('no_ext');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'colonia')) {
                $t->string('colonia', 255)->nullable()->after('no_int');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'municipio')) {
                $t->string('municipio', 255)->nullable()->after('colonia');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'estado')) {
                $t->string('estado', 255)->nullable()->after('municipio');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'cp')) {
                $t->string('cp', 20)->nullable()->after('estado');
            }

            // Preferencias CFDI / pago
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'regimen_fiscal')) {
                $t->string('regimen_fiscal', 10)->nullable()->after('cp');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'uso_cfdi')) {
                $t->string('uso_cfdi', 10)->nullable()->after('regimen_fiscal');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'metodo_pago')) {
                $t->string('metodo_pago', 10)->nullable()->after('uso_cfdi');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'forma_pago')) {
                $t->string('forma_pago', 10)->nullable()->after('metodo_pago');
            }

            // Leyenda PDF
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'leyenda_pdf')) {
                $t->string('leyenda_pdf', 255)->nullable()->after('forma_pago');
            }

            // Toggles PDF
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'pdf_mostrar_nombre_comercial')) {
                $t->tinyInteger('pdf_mostrar_nombre_comercial')->default(0)->after('leyenda_pdf');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', 'pdf_mostrar_telefono')) {
                $t->tinyInteger('pdf_mostrar_telefono')->default(0)->after('pdf_mostrar_nombre_comercial');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('cuentas_cliente', function (Blueprint $t) {
            // Si prefieres no borrar columnas en rollback, puedes dejarlo vacío.
            // Aquí lo dejo explícito.

            $cols = [
                'nombre_comercial',
                'pais','calle','no_ext','no_int','colonia','municipio','estado','cp',
                'regimen_fiscal','uso_cfdi','metodo_pago','forma_pago',
                'leyenda_pdf',
                'pdf_mostrar_nombre_comercial','pdf_mostrar_telefono',
            ];

            foreach ($cols as $c) {
                if (Schema::connection('mysql_clientes')->hasColumn('cuentas_cliente', $c)) {
                    $t->dropColumn($c);
                }
            }
        });
    }
};
