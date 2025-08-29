<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $conn = 'mysql_clientes';

        // Si ya existe, solo asegura columnas mínimas que usan los seeders
        if (Schema::connection($conn)->hasTable('clientes')) {
            Schema::connection($conn)->table('clientes', function (Blueprint $table) use ($conn) {
                $has = fn($c) => Schema::connection($conn)->hasColumn('clientes', $c);

                if (!$has('empresa'))          $table->string('empresa')->nullable()->after('id');
                if (!$has('razon_social'))     $table->string('razon_social')->nullable()->after('empresa');
                if (!$has('nombre_comercial')) $table->string('nombre_comercial')->nullable()->after('razon_social');
                if (!$has('rfc'))              $table->string('rfc',13)->after('nombre_comercial');
                if (!$has('estado'))           $table->string('estado')->default('activo')->after('rfc');
                if (!$has('plan_id'))          $table->unsignedBigInteger('plan_id')->nullable()->after('estado');
                if (!$has('plan'))             $table->string('plan')->nullable()->after('plan_id'); // compat
                if (!$has('timbres'))          $table->integer('timbres')->default(0)->after('plan');
                if (!$has('espacio_mb'))       $table->integer('espacio_mb')->default(0)->after('timbres');
                if (!$has('baja_at'))          $table->timestamp('baja_at')->nullable()->after('espacio_mb');
                if (!$has('created_at'))       $table->timestamps();
                if (!$has('deleted_at'))       $table->softDeletes();
            });
            return;
        }

        // Crear tabla en la conexión de CLIENTES
        Schema::connection($conn)->create('clientes', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('empresa');                  // usado por DashboardDemoSeeder
            $table->string('razon_social')->nullable(); // usado por DatabaseSeeder
            $table->string('nombre_comercial')->nullable();
            $table->string('rfc', 13);
            $table->string('estado')->default('activo');
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('plan')->nullable();         // compat si no hay FK a planes
            $table->integer('timbres')->default(0);
            $table->integer('espacio_mb')->default(0);
            $table->timestamp('baja_at')->nullable();
            $table->timestamps();
            $table->softDeletes();

            // Índices útiles
            $table->unique('rfc');
            $table->index(['estado','plan']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('clientes');
    }
};
