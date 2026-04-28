<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_clientes')->table('cfdis', function (Blueprint $table) {
            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'tipo_comprobante')) {
                $table->string('tipo_comprobante', 5)->nullable()->after('metodo_pago');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'pac_env')) {
                $table->string('pac_env', 20)->nullable()->after('tipo_comprobante');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'pac_status')) {
                $table->string('pac_status', 40)->nullable()->after('pac_env');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'pac_uuid')) {
                $table->string('pac_uuid', 64)->nullable()->after('pac_status');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'pac_response')) {
                $table->longText('pac_response')->nullable()->after('pac_uuid');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'json_enviado')) {
                $table->longText('json_enviado')->nullable()->after('pac_response');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'xml_base64')) {
                $table->longText('xml_base64')->nullable()->after('json_enviado');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'xml_timbrado')) {
                $table->longText('xml_timbrado')->nullable()->after('xml_base64');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'pdf_base64')) {
                $table->longText('pdf_base64')->nullable()->after('xml_timbrado');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'fecha_timbrado')) {
                $table->dateTime('fecha_timbrado')->nullable()->after('pdf_base64');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'sello_cfd')) {
                $table->longText('sello_cfd')->nullable()->after('fecha_timbrado');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'sello_sat')) {
                $table->longText('sello_sat')->nullable()->after('sello_cfd');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'no_certificado_sat')) {
                $table->string('no_certificado_sat', 80)->nullable()->after('sello_sat');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'no_certificado_cfd')) {
                $table->string('no_certificado_cfd', 80)->nullable()->after('no_certificado_sat');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'qr_url')) {
                $table->longText('qr_url')->nullable()->after('no_certificado_cfd');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'cadena_original')) {
                $table->longText('cadena_original')->nullable()->after('qr_url');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'timbrado_por')) {
                $table->string('timbrado_por', 120)->nullable()->default('pactopia.com')->after('cadena_original');
            }

            if (! Schema::connection('mysql_clientes')->hasColumn('cfdis', 'es_timbrado_real')) {
                $table->boolean('es_timbrado_real')->default(false)->after('timbrado_por');
            }
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('cfdis', function (Blueprint $table) {
            $columns = [
                'es_timbrado_real',
                'timbrado_por',
                'cadena_original',
                'qr_url',
                'no_certificado_cfd',
                'no_certificado_sat',
                'sello_sat',
                'sello_cfd',
                'fecha_timbrado',
                'pdf_base64',
                'xml_timbrado',
                'xml_base64',
                'json_enviado',
                'pac_response',
                'pac_uuid',
                'pac_status',
                'pac_env',
                'tipo_comprobante',
            ];

            foreach ($columns as $column) {
                if (Schema::connection('mysql_clientes')->hasColumn('cfdis', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};