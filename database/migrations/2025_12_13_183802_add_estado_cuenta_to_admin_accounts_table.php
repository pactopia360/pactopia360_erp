<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $schema = Schema::connection('mysql_admin');

        // Helper: decidir "after" seguro
        $afterForEstado = $schema->hasColumn('accounts', 'is_blocked')
            ? 'is_blocked'
            : ($schema->hasColumn('accounts', 'rfc') ? 'rfc' : null);

        $afterForModo = $schema->hasColumn('accounts', 'plan_actual')
            ? 'plan_actual'
            : ($schema->hasColumn('accounts', 'plan') ? 'plan' : null);

        $schema->table('accounts', function (Blueprint $table) use ($schema, $afterForEstado, $afterForModo) {

            if (!$schema->hasColumn('accounts', 'estado_cuenta')) {
                $col = $table->string('estado_cuenta', 30)->nullable();
                if ($afterForEstado) $col->after($afterForEstado);
            }

            // Solo si NO existe: modo_cobro
            if (!$schema->hasColumn('accounts', 'modo_cobro')) {
                $col = $table->string('modo_cobro', 15)->nullable();
                if ($afterForModo) $col->after($afterForModo);
            }

            // OJO: plan_actual SOLO si no existe, sin depender de "after"
            if (!$schema->hasColumn('accounts', 'plan_actual')) {
                // si existe plan, la ponemos después; si no, la agregamos normal
                $col = $table->string('plan_actual', 20)->nullable();
                if ($schema->hasColumn('accounts', 'plan')) $col->after('plan');
            }
        });

        // Backfill: si hay status -> copiar a estado_cuenta
        if ($schema->hasColumn('accounts', 'status')) {
            DB::connection('mysql_admin')->statement("
                UPDATE accounts
                SET estado_cuenta = COALESCE(estado_cuenta, status)
                WHERE estado_cuenta IS NULL
            ");
        }

        // Backfill mínimo por is_blocked si aún no se llenó
        DB::connection('mysql_admin')->statement("
            UPDATE accounts
            SET estado_cuenta = COALESCE(
                estado_cuenta,
                CASE WHEN IFNULL(is_blocked,0)=1 THEN 'bloqueada_pago' ELSE 'operando' END
            )
            WHERE estado_cuenta IS NULL
        ");
    }

    public function down(): void
    {
        $schema = Schema::connection('mysql_admin');

        $schema->table('accounts', function (Blueprint $table) use ($schema) {
            if ($schema->hasColumn('accounts', 'estado_cuenta')) $table->dropColumn('estado_cuenta');
            if ($schema->hasColumn('accounts', 'modo_cobro'))     $table->dropColumn('modo_cobro');
            if ($schema->hasColumn('accounts', 'plan_actual'))   $table->dropColumn('plan_actual');
        });
    }
};
