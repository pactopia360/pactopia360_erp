<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_admin';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable('planes')) return;

        Schema::connection($this->conn)->table('planes', function (Blueprint $table) {
            if (!Schema::connection($this->conn)->hasColumn('planes','costo_mensual')) {
                $table->decimal('costo_mensual',10,2)->default(0)->after('nombre');
            }
            if (!Schema::connection($this->conn)->hasColumn('planes','costo_anual')) {
                $table->decimal('costo_anual',10,2)->default(0)->after('costo_mensual');
            }
            if (!Schema::connection($this->conn)->hasColumn('planes','activo')) {
                $table->boolean('activo')->default(true)->after('costo_anual');
            }
            // por si falta timestamps
            if (!Schema::connection($this->conn)->hasColumn('planes','created_at')) {
                $table->timestamps();
            }
        });
    }

    public function down(): void
    {
        // No-op (evitar pérdida de datos)
    }
};
