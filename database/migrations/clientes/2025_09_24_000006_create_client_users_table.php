<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // IMPORTANTE: público y SIN tipo
    public $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = Schema::connection($this->connection);
        $table = 'users';

        // Si ya existe, no hacemos nada (idempotente)
        if ($conn->hasTable($table)) {
            return;
        }

        // Si NO existe, la creamos (por compatibilidad con instalaciones limpias)
        $conn->create($table, function (Blueprint $table) {
            $table->id();                                // bigint unsigned AI
            $table->unsignedBigInteger('account_id');    // o ajusta según tu modelo
            $table->string('name', 150);
            $table->string('email', 150);
            $table->string('phone', 30)->nullable();
            $table->string('password', 255);
            $table->boolean('must_change_password')->default(true);
            $table->rememberToken();
            $table->timestamps();

            $table->index(['email']);
            $table->index(['account_id']);
        });
    }

    public function down(): void
    {
        $conn = Schema::connection($this->connection);
        $table = 'users';

        if ($conn->hasTable($table)) {
            $conn->drop($table);
        }
    }
};
