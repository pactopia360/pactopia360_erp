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
        if (!Schema::connection($this->conn)->hasTable('usuarios_cuenta')) {
            // Si por alguna razón no existe, créala bien
            $this->createCorrect();
            return;
        }

        $schema = Schema::connection($this->conn);

        // Agrega faltantes sin usar after()
        $schema->table('usuarios_cuenta', function (Blueprint $table) use ($schema) {
            if (!$schema->hasColumn('usuarios_cuenta', 'cuenta_id')) {
                $table->unsignedBigInteger('cuenta_id')->nullable()->index();
            }

            if (!$schema->hasColumn('usuarios_cuenta', 'tipo'))   $table->string('tipo', 50)->nullable()->index();
            if (!$schema->hasColumn('usuarios_cuenta', 'rol'))    $table->string('rol', 50)->nullable()->index();
            if (!$schema->hasColumn('usuarios_cuenta', 'nombre')) $table->string('nombre', 180)->nullable();
            if (!$schema->hasColumn('usuarios_cuenta', 'email'))  $table->string('email', 191)->nullable();

            if (!$schema->hasColumn('usuarios_cuenta', 'password'))      $table->string('password', 191)->nullable();
            if (!$schema->hasColumn('usuarios_cuenta', 'password_temp')) $table->string('password_temp', 191)->nullable();

            if (!$schema->hasColumn('usuarios_cuenta', 'must_change_password')) {
                $table->boolean('must_change_password')->default(false)->index();
            }

            if (!$schema->hasColumn('usuarios_cuenta', 'activo')) {
                $table->boolean('activo')->default(true)->index();
            }

            if (!$schema->hasColumn('usuarios_cuenta', 'remember_token')) {
                $table->rememberToken();
            }
        });

        // Asegura unique email
        if ($schema->hasColumn('usuarios_cuenta', 'email')) {
            try {
                DB::connection($this->conn)->statement(
                    "ALTER TABLE `usuarios_cuenta` ADD UNIQUE KEY `uq_usuarios_cuenta_email` (`email`)"
                );
            } catch (\Throwable $e) {
                // si ya existe, ignora
            }
        }
    }

    private function createCorrect(): void
    {
        Schema::connection($this->conn)->create('usuarios_cuenta', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->unsignedBigInteger('cuenta_id')->nullable()->index();

            $table->string('tipo', 50)->nullable()->index();
            $table->string('rol', 50)->nullable()->index();
            $table->string('nombre', 180)->nullable();
            $table->string('email', 191)->nullable();

            $table->string('password', 191)->nullable();
            $table->string('password_temp', 191)->nullable();
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
        // no destructivo
    }
};
