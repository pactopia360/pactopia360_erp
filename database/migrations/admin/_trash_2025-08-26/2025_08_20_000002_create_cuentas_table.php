<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mysql_admin'; // <-- fuerza conexión admin

    public function up(): void {
        $conn = 'mysql_admin';

        // Si ya existe, solo asegura columnas/índices que usamos
        if (Schema::connection($conn)->hasTable('cuentas')) {
            Schema::connection($conn)->table('cuentas', function (Blueprint $table) use ($conn) {
                // id (uuid primario)
                if (!Schema::connection($conn)->hasColumn('cuentas','id')) {
                    $table->uuid('id')->primary();
                }

                if (!Schema::connection($conn)->hasColumn('cuentas','rfc_padre')) {
                    $table->string('rfc_padre',13)->after('id');
                }
                if (!Schema::connection($conn)->hasColumn('cuentas','razon_social')) {
                    $table->string('razon_social')->after('rfc_padre');
                }
                if (!Schema::connection($conn)->hasColumn('cuentas','codigo_cliente')) {
                    $table->string('codigo_cliente')->after('razon_social');
                }
                if (!Schema::connection($conn)->hasColumn('cuentas','email_principal')) {
                    $table->string('email_principal')->after('codigo_cliente');
                }
                if (!Schema::connection($conn)->hasColumn('cuentas','email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable()->after('email_principal');
                }
                if (!Schema::connection($conn)->hasColumn('cuentas','plan_id')) {
                    $table->unsignedBigInteger('plan_id')->nullable()->after('email_verified_at');
                }
                if (!Schema::connection($conn)->hasColumn('cuentas','estado')) {
                    $table->string('estado')->default('free')->after('plan_id');
                }
                if (!Schema::connection($conn)->hasColumn('cuentas','timbres')) {
                    $table->integer('timbres')->default(0)->after('estado');
                }
                if (!Schema::connection($conn)->hasColumn('cuentas','espacio_mb')) {
                    $table->integer('espacio_mb')->default(0)->after('timbres');
                }
                // timestamps
                if (!Schema::connection($conn)->hasColumn('cuentas','created_at')) {
                    $table->timestamps();
                }
                // soft deletes
                if (!Schema::connection($conn)->hasColumn('cuentas','deleted_at')) {
                    $table->softDeletes();
                }

                // índices/únicos (verifica que existan antes de crear)
                // RFC único
                // Nota: Laravel no ofrece "hasIndex" simple; si ya lo tienes, este createUnique será ignorado al fallar.
                try { $table->unique('rfc_padre'); } catch (\Throwable $e) {}
                try { $table->unique('codigo_cliente'); } catch (\Throwable $e) {}

                // FK a planes si aplica
                try { $table->foreign('plan_id')->references('id')->on('planes'); } catch (\Throwable $e) {}
            });
            return;
        }

        // Crear tabla desde cero (conexión admin)
        Schema::connection($conn)->create('cuentas', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('rfc_padre', 13)->unique();
            $table->string('razon_social');
            $table->string('codigo_cliente')->unique();
            $table->string('email_principal');
            $table->timestamp('email_verified_at')->nullable();
            $table->unsignedBigInteger('plan_id')->nullable();
            $table->string('estado')->default('free'); // free, premium
            $table->integer('timbres')->default(0);
            $table->integer('espacio_mb')->default(0);
            $table->timestamps();
            $table->softDeletes();
            $table->foreign('plan_id')->references('id')->on('planes');
        });
    }

    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('cuentas');
    }
};
