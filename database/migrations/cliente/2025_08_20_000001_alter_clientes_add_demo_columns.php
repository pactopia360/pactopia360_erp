<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_clientes';
    protected string $table = 'clientes';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            Schema::connection($this->conn)->create($this->table, function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('empresa')->nullable();   // nombre empresa (para el demo)
                $table->string('rfc', 20)->nullable()->index();
                $table->string('plan', 30)->nullable();  // 'free','basic','pro','premium' (demo)
                $table->unsignedInteger('timbres')->default(0);
                $table->timestamp('baja_at')->nullable();
                $table->string('estado', 20)->nullable(); // 'activo','inactivo'
                $table->timestamps();
                $table->softDeletes(); // agrega 'deleted_at'
            });
            return;
        }

        Schema::connection($this->conn)->table($this->table, function (Blueprint $table) {
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'empresa')) {
                $table->string('empresa')->nullable()->after('id');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'estado')) {
                $table->string('estado', 20)->nullable()->after('empresa');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'plan')) {
                $table->string('plan', 30)->nullable()->after('estado');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'rfc')) {
                $table->string('rfc', 20)->nullable()->after('plan');
                $table->index('rfc');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'timbres')) {
                $table->unsignedInteger('timbres')->default(0)->after('rfc');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'baja_at')) {
                $table->timestamp('baja_at')->nullable()->after('timbres');
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'deleted_at')) {
                $table->softDeletes(); // crea 'deleted_at'
            }
            if (!Schema::connection($this->conn)->hasColumn($this->table, 'created_at')) {
                $table->timestamps(); // crea created_at / updated_at si no existen
            }
        });
    }

    public function down(): void
    {
        // No eliminamos columnas (compat). Si necesitas revertir, haz una migration específica.
    }
};
