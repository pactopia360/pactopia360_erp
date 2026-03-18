<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $adm = config('p360.conn.admin') ?: 'mysql_admin';

        if (Schema::connection($adm)->hasTable('billing_invoice_taxes')) {
            return;
        }

        Schema::connection($adm)->create('billing_invoice_taxes', function (Blueprint $t) {

            $t->bigIncrements('id');

            $t->unsignedBigInteger('invoice_id')->index();
            $t->unsignedBigInteger('item_id')->nullable()->index();

            $t->string('scope',20)->default('item');

            $t->string('tax_type',20);

            $t->string('impuesto',10);
            $t->string('tipo_factor',20);

            $t->decimal('tasa_o_cuota',14,6)->nullable();

            $t->decimal('base',14,6)->default(0);
            $t->decimal('importe',14,6)->default(0);

            $t->json('meta')->nullable();

            $t->timestamps();

        });
    }

    public function down(): void
    {
        $adm = config('p360.conn.admin') ?: 'mysql_admin';

        Schema::connection($adm)->dropIfExists('billing_invoice_taxes');
    }
};