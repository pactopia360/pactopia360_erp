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

        if (!Schema::connection($c)->hasTable('finance_sales')) {
            Schema::connection($c)->create('finance_sales', function (Blueprint $t) {
                $t->bigIncrements('id');

                $t->char('account_id', 36)->nullable()->index();
                $t->string('sale_code', 80)->nullable()->index();

                $t->string('receiver_rfc', 20)->nullable()->index();
                $t->string('pay_method', 60)->nullable();

                $t->string('origin', 20)->default('no_recurrente')->index();
                $t->string('periodicity', 20)->default('unico')->index();
                $t->string('period', 7)->nullable()->index();

                $t->date('sale_date')->nullable();
                $t->date('f_cta')->nullable();
                $t->date('f_mov')->nullable();
                $t->date('invoice_date')->nullable();
                $t->date('paid_date')->nullable();

                $t->decimal('subtotal', 14, 2)->default(0);
                $t->decimal('iva', 14, 2)->default(0);
                $t->decimal('total', 14, 2)->default(0);

                $t->string('statement_status', 20)->default('pending')->index();
                $t->string('invoice_status', 40)->default('sin_solicitud')->index();
                $t->string('cfdi_uuid', 64)->nullable()->index();

                $t->unsignedBigInteger('vendor_id')->nullable()->index();

                $t->boolean('include_in_statement')->default(false)->index();
                $t->string('statement_period_target', 7)->nullable()->index();
                $t->unsignedBigInteger('statement_id')->nullable()->index();
                $t->unsignedBigInteger('statement_item_id')->nullable()->index();

                $t->text('notes')->nullable();
                $t->json('meta')->nullable();

                $t->timestamps();

                $t->foreign('vendor_id')->references('id')->on('finance_vendors')->nullOnDelete();
            });

            return;
        }

        // ✅ Si ya existe: completar columnas faltantes (modo safe)
        Schema::connection($c)->table('finance_sales', function (Blueprint $t) use ($c) {

            $cols = [
                'account_id' => fn() => $t->char('account_id', 36)->nullable(),
                'sale_code' => fn() => $t->string('sale_code', 80)->nullable(),

                'receiver_rfc' => fn() => $t->string('receiver_rfc', 20)->nullable(),
                'pay_method' => fn() => $t->string('pay_method', 60)->nullable(),

                'origin' => fn() => $t->string('origin', 20)->default('no_recurrente'),
                'periodicity' => fn() => $t->string('periodicity', 20)->default('unico'),
                'period' => fn() => $t->string('period', 7)->nullable(),

                'sale_date' => fn() => $t->date('sale_date')->nullable(),
                'f_cta' => fn() => $t->date('f_cta')->nullable(),
                'f_mov' => fn() => $t->date('f_mov')->nullable(),
                'invoice_date' => fn() => $t->date('invoice_date')->nullable(),
                'paid_date' => fn() => $t->date('paid_date')->nullable(),

                'subtotal' => fn() => $t->decimal('subtotal', 14, 2)->default(0),
                'iva' => fn() => $t->decimal('iva', 14, 2)->default(0),
                'total' => fn() => $t->decimal('total', 14, 2)->default(0),

                'statement_status' => fn() => $t->string('statement_status', 20)->default('pending'),
                'invoice_status' => fn() => $t->string('invoice_status', 40)->default('sin_solicitud'),
                'cfdi_uuid' => fn() => $t->string('cfdi_uuid', 64)->nullable(),

                'vendor_id' => fn() => $t->unsignedBigInteger('vendor_id')->nullable(),

                'include_in_statement' => fn() => $t->boolean('include_in_statement')->default(false),
                'statement_period_target' => fn() => $t->string('statement_period_target', 7)->nullable(),
                'statement_id' => fn() => $t->unsignedBigInteger('statement_id')->nullable(),
                'statement_item_id' => fn() => $t->unsignedBigInteger('statement_item_id')->nullable(),

                'notes' => fn() => $t->text('notes')->nullable(),
                'meta' => fn() => $t->json('meta')->nullable(),
                'created_at' => fn() => $t->timestamp('created_at')->nullable(),
                'updated_at' => fn() => $t->timestamp('updated_at')->nullable(),
            ];

            foreach ($cols as $col => $add) {
                if (!Schema::connection($c)->hasColumn('finance_sales', $col)) {
                    $add();
                }
            }
        });

        // Índices “try/catch” (no rompe si ya existen)
        foreach ([
            ['finance_sales_account_id_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_account_id_index (account_id)'],
            ['finance_sales_sale_code_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_sale_code_index (sale_code)'],
            ['finance_sales_receiver_rfc_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_receiver_rfc_index (receiver_rfc)'],
            ['finance_sales_origin_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_origin_index (origin)'],
            ['finance_sales_periodicity_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_periodicity_index (periodicity)'],
            ['finance_sales_period_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_period_index (period)'],
            ['finance_sales_statement_status_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_statement_status_index (statement_status)'],
            ['finance_sales_invoice_status_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_invoice_status_index (invoice_status)'],
            ['finance_sales_cfdi_uuid_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_cfdi_uuid_index (cfdi_uuid)'],
            ['finance_sales_vendor_id_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_vendor_id_index (vendor_id)'],
            ['finance_sales_include_in_statement_index', 'ALTER TABLE finance_sales ADD INDEX finance_sales_include_in_statement_index (include_in_statement)'],
        ] as [$idx, $sql]) {
            try { DB::connection($c)->statement($sql); } catch (\Throwable $e) {}
        }

        // FK vendor_id (intentamos; si ya está, no rompe)
        try {
            DB::connection($c)->statement(
                "ALTER TABLE finance_sales
                 ADD CONSTRAINT finance_sales_vendor_id_foreign
                 FOREIGN KEY (vendor_id) REFERENCES finance_vendors(id)
                 ON DELETE SET NULL"
            );
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // ❗No hacemos drop aquí por seguridad de datos.
    }
};