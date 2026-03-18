<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $adm = config('p360.conn.admin') ?: 'mysql_admin';

        if (Schema::connection($adm)->hasTable('billing_invoice_items')) {
            return;
        }

        Schema::connection($adm)->create('billing_invoice_items', function (Blueprint $t) {

            $t->bigIncrements('id');

            $t->unsignedBigInteger('invoice_id')->index();

            $t->integer('sort_order')->default(1);

            $t->string('clave_prod_serv',20)->nullable();
            $t->string('clave_unidad',10)->nullable();
            $t->string('unidad',50)->nullable();

            $t->string('descripcion',500);

            $t->decimal('cantidad',14,6)->default(1);
            $t->decimal('valor_unitario',14,6)->default(0);
            $t->decimal('importe',14,6)->default(0);
            $t->decimal('descuento',14,6)->default(0);

            $t->string('objeto_impuesto',5)->default('02');

            $t->json('meta')->nullable();

            $t->timestamps();

        });
    }

    public function down(): void
    {
        $adm = config('p360.conn.admin') ?: 'mysql_admin';

        Schema::connection($adm)->dropIfExists('billing_invoice_items');
    }
};