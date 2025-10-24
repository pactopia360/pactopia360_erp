<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('cuentas_cliente')) {
            return;
        }

        Schema::connection($this->connection)->table('cuentas_cliente', function (Blueprint $table) {
            // Plan & cobro
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'plan_actual')) {
                $table->string('plan_actual', 20)->default('FREE')->after('razon_social'); // FREE | PRO
            }
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'modo_cobro')) {
                $table->string('modo_cobro', 20)->nullable()->after('plan_actual'); // mensual | anual | null
            }

            // Límites
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'espacio_asignado_mb')) {
                $table->integer('espacio_asignado_mb')->default(1024)->after('modo_cobro'); // FREE: 1GB, PRO: 15GB
            }
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'espacio_usado_mb')) {
                $table->integer('espacio_usado_mb')->default(0)->after('espacio_asignado_mb');
            }

            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'hits_asignados')) {
                $table->integer('hits_asignados')->default(5)->after('espacio_usado_mb'); // FREE: 5 timbres totales
            }
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'hits_usados')) {
                $table->integer('hits_usados')->default(0)->after('hits_asignados');
            }

            // Facturación masiva diaria (solo PRO)
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'max_mass_invoices_per_day')) {
                $table->integer('max_mass_invoices_per_day')->default(0)->after('hits_usados'); // FREE: 0, PRO: 100
            }
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'mass_invoices_used_today')) {
                $table->integer('mass_invoices_used_today')->default(0)->after('max_mass_invoices_per_day');
            }
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'mass_invoices_reset_at')) {
                $table->timestamp('mass_invoices_reset_at')->nullable()->after('mass_invoices_used_today');
            }

            // Multiusuario / multiempresa
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'max_usuarios')) {
                $table->integer('max_usuarios')->default(1)->after('mass_invoices_reset_at'); // FREE: 1, PRO: ilimitado (usa null o un número alto)
            }
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'max_empresas')) {
                $table->integer('max_empresas')->default(9999)->after('max_usuarios'); // FREE: multiempresa (sin límite)
            }

            // Estado de la cuenta
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'estado_cuenta')) {
                $table->string('estado_cuenta', 20)->default('activa')->after('max_empresas'); // activa | bloqueada | suspendida
            }

            // Bandera de sincronización con admin/accounts (opcional)
            if (!Schema::connection($this->connection)->hasColumn('cuentas_cliente', 'admin_account_id')) {
                $table->unsignedBigInteger('admin_account_id')->nullable()->after('estado_cuenta')->index();
            }
        });

        // Backfill básico de FREE -> límites por defecto
        DB::connection($this->connection)->table('cuentas_cliente')
            ->whereNull('plan_actual')->orWhere('plan_actual','')
            ->update([
                'plan_actual'                  => 'FREE',
                'modo_cobro'                   => null,
                'espacio_asignado_mb'          => 1024,
                'max_mass_invoices_per_day'    => 0,
                'max_usuarios'                 => 1,
                'max_empresas'                 => 9999,
                'estado_cuenta'                => 'activa',
                'mass_invoices_reset_at'       => now()->startOfDay()->addDay(), // mañana 00:00
            ]);
    }

    public function down(): void
    {
        // Mantener columnas; si necesitas revertir, elimínalas aquí con cuidado.
    }
};
