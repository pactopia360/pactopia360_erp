<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_admin';

    public function up(): void
    {
        Schema::connection($this->conn)->create('planes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('nombre', 50)->unique();        // 'free', 'premium'
            $table->decimal('costo_mensual', 10, 2)->default(0);
            $table->decimal('costo_anual', 10, 2)->default(0);
            $table->unsignedInteger('limite_timbres')->nullable();
            $table->unsignedInteger('limite_espacio_mb')->nullable(); // 1GB = 1024
            $table->boolean('activo')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists('planes');
    }
};
