<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $conn = 'mysql_admin';

    public function up(): void
    {
        if (Schema::connection($this->conn)->hasTable('planes')) {
            Schema::connection($this->conn)->table('planes', function (Blueprint $t) {
                if (!Schema::connection($this->conn)->hasColumn('planes', 'costo_mensual')) {
                    $t->decimal('costo_mensual', 10, 2)->default(0)->after('nombre');
                }
                if (!Schema::connection($this->conn)->hasColumn('planes', 'costo_anual')) {
                    $t->decimal('costo_anual', 10, 2)->default(0)->after('costo_mensual');
                }
                if (!Schema::connection($this->conn)->hasColumn('planes', 'activo')) {
                    $t->boolean('activo')->default(true)->after('limite_espacio_mb');
                }
            });
        }
    }

    public function down(): void
    {
        // sin reversa
    }
};
