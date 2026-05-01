<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_clientes';
    private string $table = 'cfdis';

    public function up(): void
    {
        if (! Schema::connection($this->conn)->hasTable($this->table)) {
            return;
        }

        Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
            $this->stringColumn($table, 'tipo_documento', 5);
            $this->stringColumn($table, 'tipo_comprobante', 5);
            $this->stringColumn($table, 'version_cfdi', 10);
            $this->stringColumn($table, 'serie', 20);
            $this->stringColumn($table, 'folio', 40);

            $this->stringColumn($table, 'emisor_credential_id', 80);
            $this->stringColumn($table, 'emisor_rfc', 20);
            $this->stringColumn($table, 'emisor_razon_social', 255);
            $this->stringColumn($table, 'razon_emisor', 255);

            $this->unsignedBigIntegerColumn($table, 'receptor_id');
            $this->unsignedBigIntegerColumn($table, 'empleado_nomina_id');

            $this->stringColumn($table, 'moneda', 10);
            $this->decimalColumn($table, 'tipo_cambio', 18, 6);
            $this->stringColumn($table, 'metodo_pago', 10);
            $this->stringColumn($table, 'forma_pago', 10);
            $this->stringColumn($table, 'uso_cfdi', 10);
            $this->stringColumn($table, 'regimen_receptor', 10);
            $this->stringColumn($table, 'cp_receptor', 10);
            $this->stringColumn($table, 'condiciones_pago', 160);

            $this->decimalColumn($table, 'descuento', 18, 2);
            $this->decimalColumn($table, 'saldo_original', 18, 2);
            $this->decimalColumn($table, 'saldo_pagado', 18, 2);
            $this->decimalColumn($table, 'saldo_pendiente', 18, 2);
            $this->stringColumn($table, 'estado_pago', 60);

            $this->stringColumn($table, 'tipo_relacion', 10);
            $this->stringColumn($table, 'uuid_relacionado', 60);

            $this->jsonColumn($table, 'rep_json');
            $this->jsonColumn($table, 'nomina_json');
            $this->jsonColumn($table, 'carta_porte_json');
            $this->jsonColumn($table, 'receptor_nomina_json');
            $this->jsonColumn($table, 'ia_fill_json');
            $this->jsonColumn($table, 'ia_fiscal_snapshot');

            $this->decimalColumn($table, 'ia_fiscal_score', 8, 2);
            $this->stringColumn($table, 'ia_fiscal_nivel', 40);

            $this->stringColumn($table, 'adenda_tipo', 80);
            $this->jsonColumn($table, 'adenda_json');
            $this->longTextColumn($table, 'adenda_xml');

            $this->longTextColumn($table, 'observaciones');
        });
    }

    public function down(): void
    {
        if (! Schema::connection($this->conn)->hasTable($this->table)) {
            return;
        }

        Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
            foreach ([
                'tipo_documento',
                'tipo_comprobante',
                'version_cfdi',
                'serie',
                'folio',
                'emisor_credential_id',
                'emisor_rfc',
                'emisor_razon_social',
                'razon_emisor',
                'receptor_id',
                'empleado_nomina_id',
                'moneda',
                'tipo_cambio',
                'metodo_pago',
                'forma_pago',
                'uso_cfdi',
                'regimen_receptor',
                'cp_receptor',
                'condiciones_pago',
                'descuento',
                'saldo_original',
                'saldo_pagado',
                'saldo_pendiente',
                'estado_pago',
                'tipo_relacion',
                'uuid_relacionado',
                'rep_json',
                'nomina_json',
                'carta_porte_json',
                'receptor_nomina_json',
                'ia_fill_json',
                'ia_fiscal_snapshot',
                'ia_fiscal_score',
                'ia_fiscal_nivel',
                'adenda_tipo',
                'adenda_json',
                'adenda_xml',
                'observaciones',
            ] as $column) {
                if (Schema::connection($this->conn)->hasColumn($this->table, $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function has(string $column): bool
    {
        return Schema::connection($this->conn)->hasColumn($this->table, $column);
    }

    private function stringColumn(Blueprint $table, string $column, int $length): void
    {
        if (! $this->has($column)) {
            $table->string($column, $length)->nullable()->index();
        }
    }

    private function unsignedBigIntegerColumn(Blueprint $table, string $column): void
    {
        if (! $this->has($column)) {
            $table->unsignedBigInteger($column)->nullable()->index();
        }
    }

    private function decimalColumn(Blueprint $table, string $column, int $precision, int $scale): void
    {
        if (! $this->has($column)) {
            $table->decimal($column, $precision, $scale)->default(0);
        }
    }

    private function jsonColumn(Blueprint $table, string $column): void
    {
        if (! $this->has($column)) {
            $table->json($column)->nullable();
        }
    }

    private function longTextColumn(Blueprint $table, string $column): void
    {
        if (! $this->has($column)) {
            $table->longText($column)->nullable();
        }
    }
};