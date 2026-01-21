<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Agrega columnas faltantes en usuarios_admin para evitar 42S22 (permisos missing).
     * - Corre sobre mysql_admin y mysql (si existen)
     * - No falla si tabla/columna no existe (idempotente)
     */
    public function up(): void
    {
        $connections = array_values(array_unique(['mysql_admin', 'mysql']));

        foreach ($connections as $conn) {
            try {
                $schema = Schema::connection($conn);

                if (!$schema->hasTable('usuarios_admin')) {
                    continue;
                }

                // ===== permisos (causa del error) =====
                if (!$schema->hasColumn('usuarios_admin', 'permisos')) {
                    $schema->table('usuarios_admin', function (Blueprint $table) {
                        // JSON si tu MySQL lo soporta; si no, lo guarda como TEXT (Laravel lo maneja)
                        $table->json('permisos')->nullable()->after('rol');
                    });
                }

                // ===== columnas “esperadas” por el módulo (no estrictas, pero mejor tenerlas) =====
                if (!$schema->hasColumn('usuarios_admin', 'es_superadmin')) {
                    $schema->table('usuarios_admin', function (Blueprint $table) {
                        $table->unsignedTinyInteger('es_superadmin')->default(0)->after('activo');
                    });
                }

                if (!$schema->hasColumn('usuarios_admin', 'force_password_change')) {
                    $schema->table('usuarios_admin', function (Blueprint $table) {
                        $table->unsignedTinyInteger('force_password_change')->default(0)->after('password');
                    });
                }

                if (!$schema->hasColumn('usuarios_admin', 'last_login_at')) {
                    $schema->table('usuarios_admin', function (Blueprint $table) {
                        $table->dateTime('last_login_at')->nullable()->after('force_password_change');
                    });
                }

                if (!$schema->hasColumn('usuarios_admin', 'last_login_ip')) {
                    $schema->table('usuarios_admin', function (Blueprint $table) {
                        $table->string('last_login_ip', 64)->nullable()->after('last_login_at');
                    });
                }

            } catch (\Throwable $e) {
                // En migraciones no tiramos el deploy por un connection faltante;
                // si quieres, puedes loggear, pero mejor “silent skip”.
                continue;
            }
        }
    }

    public function down(): void
    {
        $connections = array_values(array_unique(['mysql_admin', 'mysql']));

        foreach ($connections as $conn) {
            try {
                $schema = Schema::connection($conn);

                if (!$schema->hasTable('usuarios_admin')) {
                    continue;
                }

                // Down conservador: solo quitamos permisos (lo que causaba el error).
                if ($schema->hasColumn('usuarios_admin', 'permisos')) {
                    $schema->table('usuarios_admin', function (Blueprint $table) {
                        $table->dropColumn('permisos');
                    });
                }

            } catch (\Throwable $e) {
                continue;
            }
        }
    }
};
