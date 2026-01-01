<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Usa la conexión de clientes */
    public $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = Schema::connection($this->connection);

        if (!$conn->hasTable('password_reset_tokens')) {
            $conn->create('password_reset_tokens', function (Blueprint $table) {
                // Estructura por defecto de Laravel 10/11
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();

                // Nota: no agregamos index extra en 'email' porque ya es primary key.
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('password_reset_tokens');
    }
};
