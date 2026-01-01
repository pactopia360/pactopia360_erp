<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('usuarios_cuenta')) {
            Schema::create('usuarios_cuenta', function (Blueprint $table) {
                // Clave primaria UUID (tu seeder inserta UUIDs)
                $table->uuid('id')->primary();

                // Relación con cuentas_cliente
                $table->uuid('cuenta_id');
                $table->foreign('cuenta_id')
                      ->references('id')->on('cuentas_cliente')
                      ->cascadeOnDelete();

                // Datos del usuario de la cuenta
                $table->string('tipo', 16)->default('padre')->index(); // padre | hijo (flexible)
                $table->string('nombre', 120);
                $table->string('email', 150)->unique();
                $table->string('password'); // hash bcrypt

                // Rol y estado
                $table->string('rol', 30)->default('user')->index(); // owner | admin | user (ejemplos)
                $table->boolean('activo')->default(true)->index();

                // Auditoría de acceso
                $table->timestamp('ultimo_login_at')->nullable();
                $table->string('ip_ultimo_login', 45)->nullable();

                // Control de sincronización (si usas outbox/mirroring)
                $table->unsignedBigInteger('sync_version')->default(1);

                $table->timestamps();

                // Indices útiles
                $table->index(['cuenta_id', 'activo']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('usuarios_cuenta');
    }
};
