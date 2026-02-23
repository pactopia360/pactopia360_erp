<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function adm(): string
    {
        return (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    private function def(): string
    {
        // conexión por defecto del proyecto (donde pudo haber caído la tabla por error)
        return (string) (config('database.default') ?: 'mysql');
    }

    public function up(): void
    {
        $adm = $this->adm();
        $def = $this->def();

        $admHas = Schema::connection($adm)->hasTable('finance_account_vendor');
        $defHas = Schema::connection($def)->hasTable('finance_account_vendor');

        // 1) Si NO existe en admin, créala en admin (correcto)
        if (!$admHas) {
            Schema::connection($adm)->create('finance_account_vendor', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('account_id'); // admin account id
                $t->unsignedBigInteger('vendor_id');
                $t->date('starts_on')->nullable();
                $t->date('ends_on')->nullable();
                $t->boolean('is_primary')->default(true);
                $t->timestamps();

                $t->index(['account_id', 'is_primary'], 'fav_account_primary_idx');
                $t->index(['vendor_id'], 'fav_vendor_idx');
                $t->index(['account_id', 'starts_on'], 'fav_account_starts_idx');
                $t->index(['account_id', 'ends_on'], 'fav_account_ends_idx');

                // FK (best-effort; si ya existe o no se puede por engine, no rompe)
                // Nota: finance_vendors está en admin también.
                $t->foreign('vendor_id')
                    ->references('id')
                    ->on('finance_vendors')
                    ->nullOnDelete();
            });

            $admHas = true;
        }

        // 2) Si existe en default pero NO en admin antes, copiamos data
        //    (Esto repara instalaciones donde la tabla cayó en la DB incorrecta)
        if ($defHas) {
            // si admin ya tenía registros, no duplicamos
            $admCount = (int) DB::connection($adm)->table('finance_account_vendor')->count();

            if ($admCount === 0) {
                $rows = DB::connection($def)->table('finance_account_vendor')->get();

                foreach ($rows as $r) {
                    DB::connection($adm)->table('finance_account_vendor')->insert([
                        'id'         => $r->id ?? null,
                        'account_id'  => $r->account_id,
                        'vendor_id'   => $r->vendor_id,
                        'starts_on'   => $r->starts_on ?? null,
                        'ends_on'     => $r->ends_on ?? null,
                        'is_primary'  => (int) ($r->is_primary ?? 1),
                        'created_at'  => $r->created_at ?? now(),
                        'updated_at'  => $r->updated_at ?? now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        // No hacemos drop: evitar pérdida de datos
    }
};