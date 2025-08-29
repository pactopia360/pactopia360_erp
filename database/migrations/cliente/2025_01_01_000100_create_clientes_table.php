<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Conexión BD CLIENTES (NO tipar el tipo) */
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->connection;

        // Si NO existe → crear desde cero
        if (!Schema::connection($conn)->hasTable('clientes')) {
            Schema::connection($conn)->create('clientes', function (Blueprint $t) {
                $t->engine = 'InnoDB';

                $t->bigIncrements('id');

                $t->string('empresa');                   // Razón social / nombre comercial
                $t->string('rfc', 13)->nullable();       // RFC (opcional)
                $t->string('plan')->nullable();          // free|basic|premium...
                $t->string('estado', 32)->default('activo'); // activo|inactivo|suspendido...
                $t->timestamp('baja_at')->nullable();    // bajas / churn
                $t->timestamps();                        // created_at / updated_at

                // Índices útiles
                $t->index('empresa');
                $t->index('rfc');
                $t->index('plan');
                $t->index('estado');
                $t->index('baja_at');
                $t->index('created_at');
            });
            return;
        }

        // Si SÍ existe → agregar columnas/índices faltantes sin tocar datos
        Schema::connection($conn)->table('clientes', function (Blueprint $t) use ($conn) {
            if (!Schema::connection($conn)->hasColumn('clientes', 'empresa')) {
                $t->string('empresa')->after('id');
            }
            if (!Schema::connection($conn)->hasColumn('clientes', 'rfc')) {
                $t->string('rfc', 13)->nullable()->after('empresa');
            }
            if (!Schema::connection($conn)->hasColumn('clientes', 'plan')) {
                $t->string('plan')->nullable()->after('rfc');
            }
            if (!Schema::connection($conn)->hasColumn('clientes', 'estado')) {
                $t->string('estado', 32)->default('activo')->after('plan');
            }
            if (!Schema::connection($conn)->hasColumn('clientes', 'baja_at')) {
                $t->timestamp('baja_at')->nullable()->after('estado');
            }
            // Si faltan timestamps, agrégalos
            if (!Schema::connection($conn)->hasColumn('clientes', 'created_at')) {
                $t->timestamps();
            }
        });

        // Índices que puedan faltar (envueltos en try/catch por si ya existen)
        try { DB::connection($conn)->statement('CREATE INDEX clientes_empresa_index ON clientes (empresa)'); } catch (\Throwable $e) {}
        try { DB::connection($conn)->statement('CREATE INDEX clientes_rfc_index ON clientes (rfc)'); } catch (\Throwable $e) {}
        try { DB::connection($conn)->statement('CREATE INDEX clientes_plan_index ON clientes (plan)'); } catch (\Throwable $e) {}
        try { DB::connection($conn)->statement('CREATE INDEX clientes_estado_index ON clientes (estado)'); } catch (\Throwable $e) {}
        try { DB::connection($conn)->statement('CREATE INDEX clientes_baja_at_index ON clientes (baja_at)'); } catch (\Throwable $e) {}
        try { DB::connection($conn)->statement('CREATE INDEX clientes_created_at_index ON clientes (created_at)'); } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('clientes');
    }
};
