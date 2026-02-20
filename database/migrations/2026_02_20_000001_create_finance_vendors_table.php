<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function conn(): string
    {
        return (string) (config('p360.conn.admin') ?: 'mysql_admin');
    }

    public function up(): void
    {
        $c = $this->conn();

        if (!Schema::connection($c)->hasTable('finance_vendors')) {
            Schema::connection($c)->create('finance_vendors', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('name', 140);
                $t->string('email', 190)->nullable();
                $t->string('phone', 40)->nullable();
                $t->decimal('default_commission_pct', 7, 3)->nullable(); // 0..100 con 3 decimales
                $t->boolean('is_active')->default(true);
                $t->json('meta')->nullable();
                $t->timestamps();

                $t->index('is_active');
                $t->index('name');
                $t->index('email');
            });

            return;
        }

        // ✅ Si ya existe: solo completar columnas/índices faltantes
        Schema::connection($c)->table('finance_vendors', function (Blueprint $t) use ($c) {
            if (!Schema::connection($c)->hasColumn('finance_vendors', 'name')) {
                $t->string('name', 140)->after('id');
            }
            if (!Schema::connection($c)->hasColumn('finance_vendors', 'email')) {
                $t->string('email', 190)->nullable()->after('name');
            }
            if (!Schema::connection($c)->hasColumn('finance_vendors', 'phone')) {
                $t->string('phone', 40)->nullable()->after('email');
            }
            if (!Schema::connection($c)->hasColumn('finance_vendors', 'default_commission_pct')) {
                $t->decimal('default_commission_pct', 7, 3)->nullable()->after('phone');
            }
            if (!Schema::connection($c)->hasColumn('finance_vendors', 'is_active')) {
                $t->boolean('is_active')->default(true)->after('default_commission_pct');
            }
            if (!Schema::connection($c)->hasColumn('finance_vendors', 'meta')) {
                $t->json('meta')->nullable()->after('is_active');
            }
            if (!Schema::connection($c)->hasColumn('finance_vendors', 'created_at')) {
                $t->timestamp('created_at')->nullable();
            }
            if (!Schema::connection($c)->hasColumn('finance_vendors', 'updated_at')) {
                $t->timestamp('updated_at')->nullable();
            }
        });

        // Índices (sin hasIndex en Laravel => intentamos y tragamos error si ya existen)
        foreach ([
            ['finance_vendors_is_active_index', 'ALTER TABLE finance_vendors ADD INDEX finance_vendors_is_active_index (is_active)'],
            ['finance_vendors_name_index',      'ALTER TABLE finance_vendors ADD INDEX finance_vendors_name_index (name)'],
            ['finance_vendors_email_index',     'ALTER TABLE finance_vendors ADD INDEX finance_vendors_email_index (email)'],
        ] as [$idx, $sql]) {
            try { DB::connection($c)->statement($sql); } catch (\Throwable $e) {}
        }
    }

    public function down(): void
    {
        // ❗No tiramos la tabla si ya existía antes; evitamos data-loss.
        // Si un día quieres drop explícito, se hace en una migración dedicada.
    }
};