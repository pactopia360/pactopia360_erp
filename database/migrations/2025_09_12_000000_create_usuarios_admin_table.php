<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Aseguramos que esta migración use la conexión de admin
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        // Idempotente: crea tabla si no existe
        if (! Schema::connection($this->connection)->hasTable('usuarios_admin')) {
            Schema::connection($this->connection)->create('usuarios_admin', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 191);
                $table->string('email', 191)->unique();
                $table->string('password'); // bcrypt/argon hash
                $table->string('rol', 50)->default('admin'); // admin|superadmin|soporte, etc.
                $table->boolean('activo')->default(true);
                $table->rememberToken();
                $table->timestamps();
            });
        }

        // Índices / ajustes adicionales idempotentes
        Schema::connection($this->connection)->table('usuarios_admin', function (Blueprint $table) {
            // Unique email ya en create; si migra en BDD existente sin unique, intenta agregarlo
            try {
                $table->unique('email', 'usuarios_admin_email_unique');
            } catch (\Throwable $e) {
                // ya existe, ignorar
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('usuarios_admin');
    }
};
