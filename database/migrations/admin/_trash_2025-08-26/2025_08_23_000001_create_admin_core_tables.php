<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // PLANES (Free / Premium)
        if (!Schema::connection('mysql_admin')->hasTable('planes')) {
            Schema::connection('mysql_admin')->create('planes', function (Blueprint $t) {
                $t->id();
                $t->string('clave', 32)->unique(); // 'free', 'premium'
                $t->string('nombre', 100);
                $t->text('descripcion')->nullable();
                $t->decimal('precio_mensual',10,2)->default(0);
                $t->decimal('precio_anual',10,2)->default(0);
                $t->boolean('activo')->default(true);
                $t->timestamps();
            });
        }

        // PROMOCIONES (opcionales)
        if (!Schema::connection('mysql_admin')->hasTable('promociones')) {
            Schema::connection('mysql_admin')->create('promociones', function (Blueprint $t) {
                $t->id();
                $t->string('titulo', 120);
                $t->enum('tipo',['descuento_fijo','porcentaje']);
                $t->decimal('valor',10,2)->default(0);
                $t->unsignedBigInteger('plan_id')->nullable()->index();
                $t->date('fecha_inicio')->nullable();
                $t->date('fecha_fin')->nullable();
                $t->string('codigo_cupon', 50)->nullable()->index();
                $t->unsignedInteger('uso_maximo')->nullable();
                $t->unsignedInteger('usos_actuales')->default(0);
                $t->boolean('activa')->default(true);
                $t->timestamps();
            });
        }

        // CLIENTES/ACCOUNTS (en admin)
        if (!Schema::connection('mysql_admin')->hasTable('clientes')) {
            Schema::connection('mysql_admin')->create('clientes', function (Blueprint $t) {
                $t->id();
                $t->string('codigo_usuario', 64)->unique();
                $t->string('rfc', 20)->unique(); // maestro: único a nivel cuenta padre
                $t->string('razon_social')->nullable();
                $t->string('email')->index();
                $t->string('telefono')->nullable();
                $t->boolean('email_verificado')->default(false);
                $t->unsignedBigInteger('plan_id')->nullable();
                $t->enum('estatus',['free','pro','bloqueado'])->default('free');
                $t->unsignedInteger('espacio_gb')->default(1); // free 1GB; pro 50GB (actualizable)
                $t->unsignedInteger('hits_asignados')->default(20); // free default
                $t->unsignedInteger('hits_consumidos')->default(0);
                $t->timestamp('ultimo_pago_at')->nullable();
                $t->timestamps();
            });
        }

        // USUARIOS ADMIN (staff de Pactopia)
        if (!Schema::connection('mysql_admin')->hasTable('usuarios_admin')) {
            Schema::connection('mysql_admin')->create('usuarios_admin', function (Blueprint $t) {
                $t->id();
                $t->string('nombre');
                $t->string('email')->unique();
                $t->string('password');
                $t->string('rol', 40)->default('soporte'); // superadmin, ventas, contabilidad, soporte, dev
                $t->rememberToken();
                $t->timestamps();
            });
        }

        // SUSCRIPCIONES (por cliente)
        if (!Schema::connection('mysql_admin')->hasTable('suscripciones')) {
            Schema::connection('mysql_admin')->create('suscripciones', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id')->index();
                $t->unsignedBigInteger('plan_id')->nullable();
                $t->enum('periodo',['mensual','anual'])->default('mensual');
                $t->enum('estatus',['activa','pendiente','vencida','cancelada'])->default('pendiente');
                $t->date('inicio')->nullable();
                $t->date('fin')->nullable(); // fecha de corte
                $t->timestamps();
            });
        }

        // PAGOS (admin manda)
        if (!Schema::connection('mysql_admin')->hasTable('pagos')) {
            Schema::connection('mysql_admin')->create('pagos', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cliente_id')->nullable()->index();
                $t->unsignedBigInteger('suscripcion_id')->nullable()->index();
                $t->enum('moneda',['MXN'])->default('MXN');
                $t->decimal('monto',10,2);
                $t->enum('status',['pending','paid','failed','cancelled'])->default('pending');
                $t->string('metodo', 40)->nullable(); // stripe, conekta, transferencia
                $t->string('referencia', 64)->nullable()->unique();
                $t->timestamp('fecha')->useCurrent();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_admin')->dropIfExists('pagos');
        Schema::connection('mysql_admin')->dropIfExists('suscripciones');
        Schema::connection('mysql_admin')->dropIfExists('usuarios_admin');
        Schema::connection('mysql_admin')->dropIfExists('clientes');
        Schema::connection('mysql_admin')->dropIfExists('promociones');
        Schema::connection('mysql_admin')->dropIfExists('planes');
    }
};
