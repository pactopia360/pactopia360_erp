<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_admin';

    public function up(): void
    {
        // usuario_administrativos: es_superadmin, remember_token
        if (Schema::connection($this->conn)->hasTable('usuario_administrativos')) {
            Schema::connection($this->conn)->table('usuario_administrativos', function (Blueprint $t) {
                if (!Schema::connection($this->connection ?? $this->conn)->hasColumn('usuario_administrativos','es_superadmin')) {
                    $t->boolean('es_superadmin')->default(false)->after('activo');
                }
                if (!Schema::connection($this->connection ?? $this->conn)->hasColumn('usuario_administrativos','remember_token')) {
                    $t->rememberToken();
                }
            });
        }

        // planes: costo_mensual, costo_anual, activo
        if (Schema::connection($this->conn)->hasTable('planes')) {
            Schema::connection($this->conn)->table('planes', function (Blueprint $t) {
                if (!Schema::connection($this->connection ?? $this->conn)->hasColumn('planes','costo_mensual')) {
                    $t->decimal('costo_mensual',10,2)->default(0)->after('nombre');
                }
                if (!Schema::connection($this->connection ?? $this->conn)->hasColumn('planes','costo_anual')) {
                    $t->decimal('costo_anual',10,2)->default(0)->after('costo_mensual');
                }
                if (!Schema::connection($this->connection ?? $this->conn)->hasColumn('planes','activo')) {
                    $t->boolean('activo')->default(true)->after('limite_espacio_mb');
                }
            });
        }
    }

    public function down(): void
    {
        // No eliminamos columnas para no romper datos existentes
    }
};
