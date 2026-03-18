<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';
    private string $tableName = 'receptores';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable($this->tableName)) {
            return;
        }

        Schema::connection($this->connection)->table($this->tableName, function (Blueprint $table): void {
            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'nombre_comercial')) {
                $table->string('nombre_comercial', 255)->nullable()->after('razon_social');
            }

            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'codigo_postal')) {
                $table->string('codigo_postal', 10)->nullable()->after('regimen_fiscal');
            }

            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'pais')) {
                $table->string('pais', 3)->nullable()->after('codigo_postal');
            }

            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'estado')) {
                $table->string('estado', 120)->nullable()->after('pais');
            }

            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'municipio')) {
                $table->string('municipio', 120)->nullable()->after('estado');
            }

            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'colonia')) {
                $table->string('colonia', 120)->nullable()->after('municipio');
            }

            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'calle')) {
                $table->string('calle', 180)->nullable()->after('colonia');
            }

            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'no_ext')) {
                $table->string('no_ext', 30)->nullable()->after('calle');
            }

            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'no_int')) {
                $table->string('no_int', 30)->nullable()->after('no_ext');
            }

            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'forma_pago')) {
                $table->string('forma_pago', 10)->nullable()->after('uso_cfdi');
            }

            if (!Schema::connection($this->connection)->hasColumn($this->tableName, 'metodo_pago')) {
                $table->string('metodo_pago', 10)->nullable()->after('forma_pago');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection($this->connection)->hasTable($this->tableName)) {
            return;
        }

        Schema::connection($this->connection)->table($this->tableName, function (Blueprint $table): void {
            $drop = [];

            foreach ([
                'metodo_pago',
                'forma_pago',
                'no_int',
                'no_ext',
                'calle',
                'colonia',
                'municipio',
                'estado',
                'pais',
                'codigo_postal',
                'nombre_comercial',
            ] as $col) {
                if (Schema::connection($this->connection)->hasColumn($this->tableName, $col)) {
                    $drop[] = $col;
                }
            }

            if (!empty($drop)) {
                $table->dropColumn($drop);
            }
        });
    }
};