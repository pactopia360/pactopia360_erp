<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        Schema::connection($this->connection)->table('empleados_nomina', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('empleados_nomina', 'metodo_asistencia')) {
                $table->string('metodo_asistencia', 30)->nullable()->after('activo')->index();
            }

            if (!Schema::connection($this->connection)->hasColumn('empleados_nomina', 'codigo_biometrico')) {
                $table->string('codigo_biometrico', 80)->nullable()->after('metodo_asistencia')->index();
            }

            if (!Schema::connection($this->connection)->hasColumn('empleados_nomina', 'pin_asistencia')) {
                $table->string('pin_asistencia', 20)->nullable()->after('codigo_biometrico');
            }

            if (!Schema::connection($this->connection)->hasColumn('empleados_nomina', 'telefono_whatsapp')) {
                $table->string('telefono_whatsapp', 30)->nullable()->after('pin_asistencia')->index();
            }

            if (!Schema::connection($this->connection)->hasColumn('empleados_nomina', 'dispositivo_biometrico')) {
                $table->string('dispositivo_biometrico', 120)->nullable()->after('telefono_whatsapp');
            }

            if (!Schema::connection($this->connection)->hasColumn('empleados_nomina', 'sincronizar_asistencia')) {
                $table->boolean('sincronizar_asistencia')->default(false)->after('dispositivo_biometrico');
            }

            if (!Schema::connection($this->connection)->hasColumn('empleados_nomina', 'meta_asistencia')) {
                $table->json('meta_asistencia')->nullable()->after('sincronizar_asistencia');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('empleados_nomina', function (Blueprint $table) {
            foreach ([
                'meta_asistencia',
                'sincronizar_asistencia',
                'dispositivo_biometrico',
                'telefono_whatsapp',
                'pin_asistencia',
                'codigo_biometrico',
                'metodo_asistencia',
            ] as $column) {
                if (Schema::connection($this->connection)->hasColumn('empleados_nomina', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};