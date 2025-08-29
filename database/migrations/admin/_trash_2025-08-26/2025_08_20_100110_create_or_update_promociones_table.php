<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_admin';
    protected string $table = 'promociones';

    public function up(): void {
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            Schema::connection($this->conn)->create($this->table, function (Blueprint $t) {
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
                // FK opcional si planes está en misma conexión:
                // $t->foreign('plan_id')->references('id')->on('planes');
            });
            return;
        }

        // Si YA existe → asegura columnas mínimas (no rompe datos)
        Schema::connection($this->conn)->table($this->table, function (Blueprint $t) {
            $has = fn($c) => Schema::connection($this->conn)->hasColumn($this->table, $c);

            if (!$has('titulo'))         $t->string('titulo',100)->nullable();
            if (!$has('tipo'))           $t->enum('tipo',['descuento_fijo','porcentaje'])->nullable();
            if (!$has('valor'))          $t->decimal('valor',10,2)->default(0);
            if (!$has('plan_id'))        $t->unsignedBigInteger('plan_id')->nullable();
            if (!$has('fecha_inicio'))   $t->date('fecha_inicio')->nullable();
            if (!$has('fecha_fin'))      $t->date('fecha_fin')->nullable();
            if (!$has('codigo_cupon'))   $t->string('codigo_cupon',50)->nullable();
            if (!$has('uso_maximo'))     $t->unsignedInteger('uso_maximo')->nullable();
            if (!$has('usos_actuales'))  $t->unsignedInteger('usos_actuales')->default(0);
            if (!$has('activa'))         $t->boolean('activa')->default(true);
            if (!$has('created_at'))     $t->timestamps();
        });
    }

    public function down(): void {
        Schema::connection($this->conn)->dropIfExists($this->table);
    }
};
