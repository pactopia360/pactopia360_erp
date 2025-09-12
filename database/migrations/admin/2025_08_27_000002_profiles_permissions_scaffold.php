<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * ¡No tipar! La clase padre ya define el tipo.
     */
    protected $connection = 'mysql_admin';

    private string $tablaPerfiles = 'perfiles';
    private string $tablaPermisos = 'permisos';
    private string $tablaPivotPerfilPermiso = 'perfil_permiso';
    private string $uxPermisosClave = 'permisos_clave_unique';

    public function up(): void
    {
        // Asegura tablas base (sin recrear si ya existen)
        if (!Schema::connection($this->connection)->hasTable($this->tablaPerfiles)) {
            Schema::connection($this->connection)->create($this->tablaPerfiles, function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('clave', 191);
                $t->string('nombre', 191);
                $t->text('descripcion')->nullable();
                $t->boolean('activo')->default(true);
                $t->timestamps();
                $t->index(['clave', 'activo']);
            });
        }

        if (!Schema::connection($this->connection)->hasTable($this->tablaPermisos)) {
            Schema::connection($this->connection)->create($this->tablaPermisos, function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->string('clave', 191);
                $t->string('grupo', 191)->nullable();
                $t->string('label', 191);
                $t->text('descripcion')->nullable();
                $t->boolean('activo')->default(true);
                $t->timestamps();
                $t->index(['grupo', 'activo']);
                // Índice único SOLO si no existía (pero como es tabla nueva, no existirá)
                $t->unique('clave', $this->uxPermisosClave);
            });
        } else {
            // Si la tabla ya existe, asegurar columnas mínimas sin romper nada
            if (!Schema::connection($this->connection)->hasColumn($this->tablaPermisos, 'clave')) {
                Schema::connection($this->connection)->table($this->tablaPermisos, function (Blueprint $t) {
                    $t->string('clave', 191);
                });
            }
            if (!Schema::connection($this->connection)->hasColumn($this->tablaPermisos, 'grupo')) {
                Schema::connection($this->connection)->table($this->tablaPermisos, function (Blueprint $t) {
                    $t->string('grupo', 191)->nullable();
                });
            }
            if (!Schema::connection($this->connection)->hasColumn($this->tablaPermisos, 'label')) {
                Schema::connection($this->connection)->table($this->tablaPermisos, function (Blueprint $t) {
                    $t->string('label', 191);
                });
            }
            if (!Schema::connection($this->connection)->hasColumn($this->tablaPermisos, 'descripcion')) {
                Schema::connection($this->connection)->table($this->tablaPermisos, function (Blueprint $t) {
                    $t->text('descripcion')->nullable();
                });
            }
            if (!Schema::connection($this->connection)->hasColumn($this->tablaPermisos, 'activo')) {
                Schema::connection($this->connection)->table($this->tablaPermisos, function (Blueprint $t) {
                    $t->boolean('activo')->default(true);
                });
            }

            // Crear índice único en 'clave' SOLO si no existe
            $db = DB::connection($this->connection);
            if (!$this->indexExists($db, $this->tablaPermisos, $this->uxPermisosClave)) {
                Schema::connection($this->connection)->table($this->tablaPermisos, function (Blueprint $t) {
                    $t->unique('clave', $this->uxPermisosClave);
                });
            }
        }

        // Tabla pivot perfil_permiso (si no existe)
        if (!Schema::connection($this->connection)->hasTable($this->tablaPivotPerfilPermiso)) {
            Schema::connection($this->connection)->create($this->tablaPivotPerfilPermiso, function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('perfil_id');
                $t->unsignedBigInteger('permiso_id');
                $t->timestamps();

                $t->unique(['perfil_id', 'permiso_id'], 'ux_perfil_permiso');
                $t->index('perfil_id');
                $t->index('permiso_id');

                // Descomenta si deseas FKs (sólo si las tablas/columnas están íntegras)
                // $t->foreign('perfil_id')->references('id')->on('perfiles')->onDelete('cascade');
                // $t->foreign('permiso_id')->references('id')->on('permisos')->onDelete('cascade');
            });
        }

        // (Opcional) Semillas mínimas idempotentes: sólo si te interesa, puedes insertar aquí
        // perfiles/permiso base verificando que no existan previamente (omito para no tocar data existente).
    }

    public function down(): void
    {
        // No borres tablas productivas; sólo limpia lo que esta migración podría haber agregado
        if (Schema::connection($this->connection)->hasTable($this->tablaPivotPerfilPermiso)) {
            Schema::connection($this->connection)->drop($this->tablaPivotPerfilPermiso);
        }

        // No eliminamos 'perfiles' ni 'permisos' en down para evitar pérdida de datos/índices previos.
        // Si quisieras retirar el índice único específico que esta migración podría haber agregado:
        $db = DB::connection($this->connection);
        if ($this->indexExists($db, $this->tablaPermisos, $this->uxPermisosClave)) {
            Schema::connection($this->connection)->table($this->tablaPermisos, function (Blueprint $t) {
                $t->dropUnique($this->uxPermisosClave);
            });
        }
    }

    /**
     * Verifica si un índice existe en MySQL sin doctrine/dbal.
     */
    private function indexExists(\Illuminate\Database\Connection $db, string $table, string $indexName): bool
    {
        if ($db->getDriverName() !== 'mysql') return false;
        $res = $db->select("SHOW INDEX FROM `{$table}` WHERE `Key_name` = ?", [$indexName]);
        return !empty($res);
    }
};
