<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        // planes
        if (!Schema::connection('mysql_admin')->hasTable('planes')) {
            Schema::connection('mysql_admin')->create('planes', function (Blueprint $table) {
                $table->id();
                $table->string('slug', 32)->unique(); // free / premium
                $table->string('nombre', 64);
                $table->decimal('precio_mensual',10,2)->default(0);
                $table->decimal('precio_anual',10,2)->default(0);
                $table->json('incluye')->nullable();  // módulos/beneficios
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });
        }

        // promociones
        if (!Schema::connection('mysql_admin')->hasTable('promociones')) {
            Schema::connection('mysql_admin')->create('promociones', function (Blueprint $table) {
                $table->id();
                $table->string('titulo', 120);
                $table->enum('tipo', ['porcentaje','fijo']);
                $table->decimal('valor',10,2);
                $table->unsignedBigInteger('plan_id')->nullable()->index();
                $table->string('codigo_cupon', 50)->nullable()->index();
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_fin')->nullable();
                $table->integer('uso_maximo')->nullable();
                $table->integer('usos_actuales')->default(0);
                $table->boolean('activa')->default(true);
                $table->timestamps();

                $table->foreign('plan_id')->references('id')->on('planes')->nullOnDelete();
            });
        }

        // pagos
        if (!Schema::connection('mysql_admin')->hasTable('pagos')) {
            Schema::connection('mysql_admin')->create('pagos', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('account_id')->index();
                $table->string('referencia', 64)->unique();     // id de pasarela / folio
                $table->enum('ciclo', ['mensual','anual']);
                $table->decimal('monto',10,2);
                $table->enum('status', ['pendiente','pagado','fallido','cancelado'])->default('pendiente');
                $table->date('periodo_inicio')->nullable();
                $table->date('periodo_fin')->nullable();
                $table->json('detalle')->nullable(); // concepto, impuestos, cupon aplicado, etc.
                $table->timestamps();

                $table->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
            });
        }
    }

    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('pagos');
        Schema::connection('mysql_admin')->dropIfExists('promociones');
        Schema::connection('mysql_admin')->dropIfExists('planes');
    }
};
