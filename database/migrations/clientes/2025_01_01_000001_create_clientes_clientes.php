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

        // Si YA existe la tabla → solo asegurar columnas/índices y terminar SIN crear de nuevo
        if (Schema::connection($conn)->hasTable('clientes')) {
            Schema::connection($conn)->table('clientes', function (Blueprint $t) use ($conn) {
                // Columnas
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
                if (!Schema::connection($conn)->hasColumn('clientes', 'created_at')) {
                    $t->timestamps();
                }
            });

            // Índices (silenciosos si ya existen)
            try { DB::connection($conn)->statement('CREATE INDEX clientes_empresa_index ON clientes (empresa)'); } catch (\Throwable $e) {}
            try { DB::connection($conn)->statement('CREATE INDEX clientes_rfc_index ON clientes (rfc)'); } catch (\Throwable $e) {}
            try { DB::connection($conn)->statement('CREATE INDEX clientes_plan_index ON clientes (plan)'); } catch (\Throwable $e) {}
            try { DB::connection($conn)->statement('CREATE INDEX clientes_estado_index ON clientes (estado)'); } catch (\Throwable $e) {}
            try { DB::connection($conn)->statement('CREATE INDEX clientes_baja_at_index ON clientes (baja_at)'); } catch (\Throwable $e) {}
            try { DB::connection($conn)->statement('CREATE INDEX clientes_created_at_index ON clientes (created_at)'); } catch (\Throwable $e) {}

            return; // <- CLAVE: No intentes crear de nuevo
        }

        // Si NO existe → crear desde cero
        Schema::connection($conn)->create('clientes', function (Blueprint $t) {
            $t->engine = 'InnoDB';

            $t->bigIncrements('id');
            $t->string('empresa');
            $t->string('rfc', 13)->nullable();
            $t->string('plan')->nullable();
            $t->string('estado', 32)->default('activo');
            $t->timestamp('baja_at')->nullable();
            $t->timestamps();

            $t->index('empresa');
            $t->index('rfc');
            $t->index('plan');
            $t->index('estado');
            $t->index('baja_at');
            $t->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('clientes');
    }
};
