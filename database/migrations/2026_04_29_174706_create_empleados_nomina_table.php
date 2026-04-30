<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable('empleados_nomina')) {
            return;
        }

        Schema::connection($this->connection)->create('empleados_nomina', function (Blueprint $table) {
            $table->id();

            $table->string('cuenta_id', 64)->index();

            $table->string('numero_empleado', 60)->nullable()->index();
            $table->string('rfc', 13)->index();
            $table->string('curp', 18)->nullable()->index();
            $table->string('nss', 20)->nullable();

            $table->string('nombre', 120);
            $table->string('apellido_paterno', 120)->nullable();
            $table->string('apellido_materno', 120)->nullable();
            $table->string('nombre_completo', 255)->index();

            $table->string('email', 180)->nullable();
            $table->string('telefono', 40)->nullable();

            $table->string('codigo_postal', 10)->nullable();
            $table->string('regimen_fiscal', 10)->default('605');
            $table->string('uso_cfdi', 10)->default('CN01');

            $table->date('fecha_inicio_relacion_laboral')->nullable();
            $table->string('tipo_contrato', 10)->nullable();
            $table->string('tipo_jornada', 10)->nullable();
            $table->string('tipo_regimen', 10)->nullable();
            $table->string('periodicidad_pago', 10)->nullable();

            $table->string('departamento', 160)->nullable();
            $table->string('puesto', 160)->nullable();
            $table->string('riesgo_puesto', 10)->nullable();

            $table->decimal('salario_base_cot_apor', 14, 2)->default(0);
            $table->decimal('salario_diario_integrado', 14, 2)->default(0);

            $table->string('banco', 10)->nullable();
            $table->string('cuenta_bancaria', 30)->nullable();

            $table->boolean('sindicalizado')->default(false);
            $table->boolean('activo')->default(true);

            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->unique(['cuenta_id', 'rfc']);
            $table->unique(['cuenta_id', 'numero_empleado']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('empleados_nomina');
    }
};