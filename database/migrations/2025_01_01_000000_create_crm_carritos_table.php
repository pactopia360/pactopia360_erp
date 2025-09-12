<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('crm_carritos', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('cliente', 160);
            $table->string('email', 160)->nullable();
            $table->string('telefono', 60)->nullable();
            $table->char('moneda', 3)->default('MXN');
            $table->decimal('total', 12, 2)->default(0);
            $table->enum('estado', ['abierto','convertido','cancelado'])->default('abierto');
            $table->string('origen', 60)->nullable(); // e.g. web, landing, manual, api
            $table->json('etiquetas')->nullable();    // ["nuevo","vip"]
            $table->json('meta')->nullable();         // json libre
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->index(['estado', 'created_at']);
            $table->index('cliente');
            $table->index('email');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_carritos');
    }
};
