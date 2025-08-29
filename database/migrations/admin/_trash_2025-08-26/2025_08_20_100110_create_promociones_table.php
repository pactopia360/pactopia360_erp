<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $conn = 'mysql_admin';
        $table = 'promociones';

        if (!Schema::connection($conn)->hasTable($table)) {
            Schema::connection($conn)->create($table, function (Blueprint $t) {
                $t->id();
                $t->string('titulo', 100);
                $t->enum('tipo', ['descuento_fijo','porcentaje']);
                $t->decimal('valor', 10, 2)->default(0);
                $t->unsignedBigInteger('plan_id')->nullable();
                $t->date('fecha_inicio')->nullable();
                $t->date('fecha_fin')->nullable();
                $t->string('codigo_cupon', 50)->nullable();
                $t->unsignedInteger('uso_maximo')->nullable();
                $t->unsignedInteger('usos_actuales')->default(0);
                $t->boolean('activa')->default(true);
                $t->timestamps();
            });
            return;
        }

        // Si ya existe, solo “asegura” columnas clave (no rompe nada si ya están):
        Schema::connection($conn)->table($table, function (Blueprint $t) use ($conn, $table) {
            $has = fn($col) => Schema::connection($conn)->hasColumn($table, $col);

            if (!$has('titulo'))         $t->string('titulo',100)->after('id');
            if (!$has('tipo'))           $t->enum('tipo',['descuento_fijo','porcentaje'])->after('titulo');
            if (!$has('valor'))          $t->decimal('valor',10,2)->default(0)->after('tipo');
            if (!$has('plan_id'))        $t->unsignedBigInteger('plan_id')->nullable()->after('valor');
            if (!$has('fecha_inicio'))   $t->date('fecha_inicio')->nullable()->after('plan_id');
            if (!$has('fecha_fin'))      $t->date('fecha_fin')->nullable()->after('fecha_inicio');
            if (!$has('codigo_cupon'))   $t->string('codigo_cupon',50)->nullable()->after('fecha_fin');
            if (!$has('uso_maximo'))     $t->unsignedInteger('uso_maximo')->nullable()->after('codigo_cupon');
            if (!$has('usos_actuales'))  $t->unsignedInteger('usos_actuales')->default(0)->after('uso_maximo');
            if (!$has('activa'))         $t->boolean('activa')->default(true)->after('usos_actuales');
            if (!$has('created_at'))     $t->timestamps();
        });
    }

    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('promociones');
    }
};
