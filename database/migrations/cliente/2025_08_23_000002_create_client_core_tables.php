<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // CUENTA MAESTRA en base clientes (espejo)
        if (!Schema::connection('mysql_clientes')->hasTable('clientes')) {
            Schema::connection('mysql_clientes')->create('clientes', function (Blueprint $t) {
                $t->id();
                $t->string('codigo_usuario', 64)->unique();
                $t->string('rfc', 20)->unique();
                $t->string('razon_social')->nullable();
                $t->string('email')->index();
                $t->boolean('email_verificado')->default(false);
                $t->enum('estatus',['free','pro','bloqueado'])->default('free');
                $t->unsignedInteger('espacio_gb')->default(1);
                $t->unsignedInteger('hits_asignados')->default(20);
                $t->unsignedInteger('hits_consumidos')->default(0);
                $t->timestamps();
            });
        }

        // USUARIOS HIJOS ligados a la cuenta (multiusuario por RFC padre)
        if (!Schema::connection('mysql_clientes')->hasTable('usuarios_cliente')) {
            Schema::connection('mysql_clientes')->create('usuarios_cliente', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id')->index();
                $t->string('nombre');
                $t->string('email')->unique();
                $t->string('password');
                $t->boolean('es_admin_cuenta')->default(false);
                $t->rememberToken();
                $t->timestamps();
            });
        }

        // (Opcional) consumo de CFDI/hits por mes (se integra después con tu tabla cfdis)
        if (!Schema::connection('mysql_clientes')->hasTable('hits_movimientos')) {
            Schema::connection('mysql_clientes')->create('hits_movimientos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id')->index();
                $t->enum('tipo',['compra','consumo','ajuste'])->default('consumo');
                $t->integer('cantidad')->default(0);
                $t->string('referencia')->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('hits_movimientos');
        Schema::connection('mysql_clientes')->dropIfExists('usuarios_cliente');
        Schema::connection('mysql_clientes')->dropIfExists('clientes');
    }
};
