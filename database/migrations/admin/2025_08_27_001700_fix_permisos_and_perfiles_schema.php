<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /** -------- permisos -------- */
        if (Schema::hasTable('permisos')) {
            Schema::table('permisos', function (Blueprint $t) {
                if (!Schema::hasColumn('permisos', 'grupo')) {
                    $t->string('grupo', 120)->nullable()->after('clave')->index();
                }
                if (!Schema::hasColumn('permisos', 'label')) {
                    $t->string('label', 180)->nullable()->after('grupo');
                }
                if (!Schema::hasColumn('permisos', 'activo')) {
                    $t->tinyInteger('activo')->default(1)->after('label');
                }
            });

            // Asegurar UNIQUE en clave solo si no existe ya
            $existsUnique = collect(DB::select("
                SELECT 1
                FROM information_schema.statistics
                WHERE table_schema = DATABASE()
                  AND table_name = 'permisos'
                  AND index_name = 'permisos_clave_unique'
                  AND non_unique = 0
                LIMIT 1
            "))->isNotEmpty();

            if (!$existsUnique) {
                // Verifica que no exista otro índice UNIQUE anónimo sobre 'clave'
                $existsAnyUniqueOnClave = collect(DB::select("
                    SELECT 1
                    FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = 'permisos'
                      AND column_name = 'clave'
                      AND non_unique = 0
                    LIMIT 1
                "))->isNotEmpty();

                if (!$existsAnyUniqueOnClave) {
                    Schema::table('permisos', function (Blueprint $t) {
                        $t->unique('clave', 'permisos_clave_unique');
                    });
                }
            }
        }

        /** -------- perfiles (usar tu esquema: clave, nombre, descripcion, activo) -------- */
        if (Schema::hasTable('perfiles')) {
            Schema::table('perfiles', function (Blueprint $t) {
                if (!Schema::hasColumn('perfiles', 'clave')) {
                    $t->string('clave', 120)->nullable()->unique()->after('id');
                }
                if (!Schema::hasColumn('perfiles', 'nombre')) {
                    $t->string('nombre', 120)->after('clave')->nullable();
                }
                if (!Schema::hasColumn('perfiles', 'descripcion')) {
                    $t->text('descripcion')->nullable()->after('nombre');
                }
                if (!Schema::hasColumn('perfiles', 'activo')) {
                    $t->tinyInteger('activo')->default(1)->after('descripcion')->index();
                }
            });

            // Si la columna clave no es única, intenta crear índice único con nombre estable
            if (Schema::hasColumn('perfiles','clave')) {
                $unique = collect(DB::select("
                    SELECT 1 FROM information_schema.statistics
                    WHERE table_schema = DATABASE()
                      AND table_name = 'perfiles'
                      AND index_name = 'perfiles_clave_unique'
                      AND non_unique = 0
                    LIMIT 1
                "))->isNotEmpty();
                if (!$unique) {
                    $anyUniqueOnClave = collect(DB::select("
                        SELECT 1 FROM information_schema.statistics
                        WHERE table_schema = DATABASE()
                          AND table_name = 'perfiles'
                          AND column_name = 'clave'
                          AND non_unique = 0
                        LIMIT 1
                    "))->isNotEmpty();
                    if (!$anyUniqueOnClave) {
                        Schema::table('perfiles', function (Blueprint $t) {
                            $t->unique('clave','perfiles_clave_unique');
                        });
                    }
                }
            }
        }

        /** -------- pivot perfil_permiso -------- */
        if (!Schema::hasTable('perfil_permiso')) {
            Schema::create('perfil_permiso', function (Blueprint $t) {
                $t->unsignedBigInteger('perfil_id');
                $t->unsignedBigInteger('permiso_id');
                $t->primary(['perfil_id','permiso_id']);
                $t->index('perfil_id');
                $t->index('permiso_id');
            });
        }
    }

    public function down(): void
    {
        // No hacemos rollbacks destructivos aquí por seguridad
    }
};
