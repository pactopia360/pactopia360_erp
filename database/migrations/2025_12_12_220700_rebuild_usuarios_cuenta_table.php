<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_clientes';

    public function up(): void
    {
        // Si no existe, la crea bien y sale.
        if (!Schema::connection($this->conn)->hasTable('usuarios_cuenta')) {
            $this->createCorrectTable();
            return;
        }

        // Detectar si está "mal creada" (id bigint auto_increment + casi sin columnas)
        $cols = DB::connection($this->conn)->select("SHOW COLUMNS FROM `usuarios_cuenta`");
        $colNames = array_map(fn($r) => $r->Field, $cols);

        $idRow = collect($cols)->firstWhere('Field', 'id');
        $idType = $idRow?->Type ?? '';

        $looksBroken =
            str_contains(strtolower($idType), 'bigint') // id bigint
            && !in_array('email', $colNames, true)
            && !in_array('password', $colNames, true)
            && !in_array('cuenta_id', $colNames, true);

        if ($looksBroken) {
            // No hay datos útiles; reconstruimos correctamente.
            Schema::connection($this->conn)->drop('usuarios_cuenta');
            $this->createCorrectTable();
            return;
        }

        // Si existe pero le faltan columnas, aseguramos las mínimas esperadas por el modelo.
        Schema::connection($this->conn)->table('usuarios_cuenta', function (Blueprint $table) use ($colNames) {

            if (!in_array('cuenta_id', $colNames, true)) {
                $table->uuid('cuenta_id')->nullable()->index();
            }

            if (!in_array('tipo', $colNames, true)) {
                $table->string('tipo', 50)->nullable()->index();
            }

            if (!in_array('rol', $colNames, true)) {
                $table->string('rol', 50)->nullable()->index();
            }

            if (!in_array('nombre', $colNames, true)) {
                $table->string('nombre', 180)->nullable();
            }

            if (!in_array('email', $colNames, true)) {
                $table->string('email', 191)->nullable()->index();
            }

            if (!in_array('password', $colNames, true)) {
                $table->string('password')->nullable();
            }

            if (!in_array('password_temp', $colNames, true)) {
                $table->string('password_temp')->nullable();
            }

            if (!in_array('must_change_password', $colNames, true)) {
                $table->boolean('must_change_password')->default(false)->index();
            }

            if (!in_array('activo', $colNames, true)) {
                $table->boolean('activo')->default(true)->index();
            }

            if (!in_array('remember_token', $colNames, true)) {
                $table->rememberToken();
            }
        });

        // Asegura unique email si existe la columna
        $hasEmail = Schema::connection($this->conn)->hasColumn('usuarios_cuenta', 'email');
        if ($hasEmail) {
            try {
                DB::connection($this->conn)->statement("ALTER TABLE `usuarios_cuenta` ADD UNIQUE KEY `uq_usuarios_cuenta_email` (`email`)");
            } catch (\Throwable $e) {
                // si ya existe el índice, ignorar
            }
        }
    }

    private function createCorrectTable(): void
    {
        Schema::connection($this->conn)->create('usuarios_cuenta', function (Blueprint $table) {
            $table->uuid('id')->primary();

            $table->uuid('cuenta_id')->nullable()->index();

            $table->string('tipo', 50)->nullable()->index();
            $table->string('rol', 50)->nullable()->index();
            $table->string('nombre', 180)->nullable();
            $table->string('email', 191)->nullable();

            $table->string('password')->nullable();
            $table->string('password_temp')->nullable();
            $table->boolean('must_change_password')->default(false)->index();
            $table->boolean('activo')->default(true)->index();

            $table->rememberToken();
            $table->timestamps();

            $table->unique('email', 'uq_usuarios_cuenta_email');
            $table->index(['cuenta_id', 'activo'], 'ix_uc_cuenta_activo');
        });
    }

    public function down(): void
    {
        // No hacemos rollback destructivo aquí.
    }
};
