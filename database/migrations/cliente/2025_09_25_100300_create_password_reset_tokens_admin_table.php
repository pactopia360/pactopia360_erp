<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** Usa la conexión de admin */
    public $connection = 'mysql_admin';

    public function up(): void
    {
        $conn = Schema::connection($this->connection);

        if (!$conn->hasTable('password_reset_tokens')) {
            $conn->create('password_reset_tokens', function (Blueprint $table) {
                $table->string('email')->primary();
                $table->string('token');
                $table->timestamp('created_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('password_reset_tokens');
    }
};
