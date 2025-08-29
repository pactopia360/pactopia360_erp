<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Conexión BD ADMIN (NO tipar el tipo) */
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        $conn = $this->connection;

        // Si YA existe la tabla → asegurar columnas/índices y terminar
        if (Schema::connection($conn)->hasTable('pagos')) {
            Schema::connection($conn)->table('pagos', function (Blueprint $t) use ($conn) {
                if (!Schema::connection($conn)->hasColumn('pagos', 'fecha')) {
                    $t->dateTime('fecha')->after('id');
                }
                if (!Schema::connection($conn)->hasColumn('pagos', 'cliente')) {
                    $t->string('cliente', 160)->nullable()->after('fecha');
                }
                if (!Schema::connection($conn)->hasColumn('pagos', 'rfc')) {
                    $t->string('rfc', 13)->nullable()->after('cliente');
                }
                if (!Schema::connection($conn)->hasColumn('pagos', 'referencia')) {
                    $t->string('referencia', 100)->nullable()->after('rfc');
                }
                if (!Schema::connection($conn)->hasColumn('pagos', 'metodo')) {
                    $t->string('metodo', 50)->nullable()->after('referencia');
                }
                if (!Schema::connection($conn)->hasColumn('pagos', 'estado')) {
                    $t->string('estado', 20)->default('pagado')->after('metodo');
                }
                if (!Schema::connection($conn)->hasColumn('pagos', 'plan')) {
                    $t->string('plan', 50)->nullable()->after('estado');
                }
                if (!Schema::connection($conn)->hasColumn('pagos', 'timbres')) {
                    $t->unsignedInteger('timbres')->default(0)->after('plan');
                }
                if (!Schema::connection($conn)->hasColumn('pagos', 'monto')) {
                    $t->decimal('monto', 12, 2)->default(0)->after('timbres');
                }
                if (!Schema::connection($conn)->hasColumn('pagos', 'created_at')) {
                    $t->timestamps();
                }
            });

            // Índices recomendados
            try { DB::connection($conn)->statement('CREATE INDEX pagos_fecha_index ON pagos (fecha)'); } catch (\Throwable $e) {}
            try { DB::connection($conn)->statement('CREATE INDEX pagos_estado_index ON pagos (estado)'); } catch (\Throwable $e) {}
            try { DB::connection($conn)->statement('CREATE INDEX pagos_plan_index ON pagos (plan)'); } catch (\Throwable $e) {}
            try { DB::connection($conn)->statement('CREATE INDEX pagos_cliente_index ON pagos (cliente)'); } catch (\Throwable $e) {}
            try { DB::connection($conn)->statement('CREATE INDEX pagos_rfc_index ON pagos (rfc)'); } catch (\Throwable $e) {}

            return; // <- CLAVE
        }

        // Si NO existe → crear
        Schema::connection($conn)->create('pagos', function (Blueprint $t) {
            $t->engine = 'InnoDB';

            $t->bigIncrements('id');
            $t->dateTime('fecha');
            $t->string('cliente', 160)->nullable();
            $t->string('rfc', 13)->nullable();
            $t->string('referencia', 100)->nullable();
            $t->string('metodo', 50)->nullable();
            $t->string('estado', 20)->default('pagado'); // pendiente|pagado
            $t->string('plan', 50)->nullable();          // free|basic|premium...
            $t->unsignedInteger('timbres')->default(0);
            $t->decimal('monto', 12, 2)->default(0);
            $t->timestamps();

            $t->index('fecha');
            $t->index('estado');
            $t->index('plan');
            $t->index('cliente');
            $t->index('rfc');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('pagos');
    }
};
