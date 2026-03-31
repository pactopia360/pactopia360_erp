<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $adm = 'mysql_admin';
    private string $cli = 'mysql_clientes';

    public function up(): void
    {
        $scAdm = Schema::connection($this->adm);
        $scCli = Schema::connection($this->cli);

        if (! $scAdm->hasTable('billing_statement_status_overrides')) {
            return;
        }

        $cliDb = (string) config("database.connections.{$this->cli}.database");
        if ($cliDb === '') {
            throw new RuntimeException("No se pudo resolver el nombre de la base para la conexión {$this->cli}");
        }

        if (! $scCli->hasTable('cuentas_cliente')) {
            throw new RuntimeException("No existe {$cliDb}.cuentas_cliente");
        }

        if (! $scCli->hasColumn('cuentas_cliente', 'id') || ! $scCli->hasColumn('cuentas_cliente', 'admin_account_id')) {
            throw new RuntimeException('cuentas_cliente no tiene columnas id/admin_account_id');
        }

        if (! $scAdm->hasColumn('billing_statement_status_overrides', 'account_id_tmp')) {
            $scAdm->table('billing_statement_status_overrides', function (Blueprint $table) {
                $table->string('account_id_tmp', 36)->nullable()->after('account_id');
            });
        }

        DB::connection($this->adm)->statement("
            UPDATE billing_statement_status_overrides o
            INNER JOIN `{$cliDb}`.`cuentas_cliente` cc
                ON cc.admin_account_id = o.account_id
            SET o.account_id_tmp = cc.id
            WHERE o.account_id IS NOT NULL
        ");

        $invalid = DB::connection($this->adm)
            ->table('billing_statement_status_overrides')
            ->whereNull('account_id_tmp')
            ->count();

        if ($invalid > 0) {
            $rows = DB::connection($this->adm)
                ->table('billing_statement_status_overrides')
                ->select('id', 'account_id', 'period')
                ->whereNull('account_id_tmp')
                ->get();

            throw new RuntimeException(
                'No se pudieron mapear ' . $invalid . ' overrides a cuentas_cliente.id UUID. Revisa admin_account_id en '
                . $cliDb . '.cuentas_cliente. Ejemplos: '
                . $rows->take(10)->map(fn ($r) => "#{$r->id}:account_id={$r->account_id}:period={$r->period}")->implode(' | ')
            );
        }

        try {
            DB::connection($this->adm)->statement("
                ALTER TABLE billing_statement_status_overrides
                DROP INDEX bsso_account_period_unique
            ");
        } catch (\Throwable $e) {
        }

        try {
            DB::connection($this->adm)->statement("
                ALTER TABLE billing_statement_status_overrides
                DROP INDEX bsso_account_period_uq
            ");
        } catch (\Throwable $e) {
        }

        try {
            DB::connection($this->adm)->statement("
                ALTER TABLE billing_statement_status_overrides
                DROP INDEX billing_statement_status_overrides_account_id_index
            ");
        } catch (\Throwable $e) {
        }

        try {
            DB::connection($this->adm)->statement("
                ALTER TABLE billing_statement_status_overrides
                DROP INDEX bsso_account_id_idx
            ");
        } catch (\Throwable $e) {
        }

        if ($scAdm->hasColumn('billing_statement_status_overrides', 'account_id')) {
            $scAdm->table('billing_statement_status_overrides', function (Blueprint $table) {
                $table->dropColumn('account_id');
            });
        }

        if ($scAdm->hasColumn('billing_statement_status_overrides', 'account_id_tmp')) {
            $scAdm->table('billing_statement_status_overrides', function (Blueprint $table) {
                $table->renameColumn('account_id_tmp', 'account_id');
            });
        }

        $scAdm->table('billing_statement_status_overrides', function (Blueprint $table) {
            $table->index('account_id', 'bsso_account_id_idx');
            $table->unique(['account_id', 'period'], 'bsso_account_period_unique');
        });
    }

    public function down(): void
    {
        // No reversible de forma segura.
    }
};