<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $adm = config('p360.conn.admin') ?: 'mysql_admin';

        if (Schema::connection($adm)->hasTable('billing_invoice_events')) {
            return;
        }

        Schema::connection($adm)->create('billing_invoice_events', function (Blueprint $t) {

            $t->bigIncrements('id');

            $t->unsignedBigInteger('invoice_id')->index();

            $t->string('event',100);

            $t->string('status_before',50)->nullable();
            $t->string('status_after',50)->nullable();

            $t->text('message')->nullable();

            $t->json('payload')->nullable();

            $t->string('actor_type',50)->nullable();
            $t->unsignedBigInteger('actor_id')->nullable();

            $t->string('ip',45)->nullable();

            $t->timestamps();

        });
    }

    public function down(): void
    {
        $adm = config('p360.conn.admin') ?: 'mysql_admin';

        Schema::connection($adm)->dropIfExists('billing_invoice_events');
    }
};