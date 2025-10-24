<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    // SIN typehints para evitar warnings de Laravel con propiedades tipadas
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        $conn = $this->connection;
        $table = 'usuario_administrativos';

        // 1) Si la tabla NO existe, créala completa con unique en email
        if (!Schema::connection($conn)->hasTable($table)) {
            Schema::connection($conn)->create($table, function (Blueprint $t) {
                $t->id();
                $t->string('nombre', 191);
                $t->string('email', 191)->unique('usuario_administrativos_email_unique');
                $t->string('password');
                $t->string('rol', 50)->default('admin');
                $t->boolean('activo')->default(true);
                $t->boolean('es_superadmin')->default(false);
                $t->boolean('force_password_change')->default(false);
                $t->timestamp('last_login_at')->nullable();
                $t->string('last_login_ip', 45)->nullable();
                $t->rememberToken();
                $t->timestamps();
            });
            return;
        }

        // 2) Si SÍ existe, añade sólo lo faltante (columnas)
        Schema::connection($conn)->table($table, function (Blueprint $t) use ($conn, $table) {
            $cols = Schema::connection($conn)->getColumnListing($table);

            if (!in_array('nombre', $cols))                $t->string('nombre', 191)->after('id');
            if (!in_array('email', $cols))                 $t->string('email', 191)->after('nombre');
            if (!in_array('password', $cols))              $t->string('password')->after('email');
            if (!in_array('rol', $cols))                   $t->string('rol', 50)->default('admin')->after('password');
            if (!in_array('activo', $cols))                $t->boolean('activo')->default(true)->after('rol');
            if (!in_array('es_superadmin', $cols))         $t->boolean('es_superadmin')->default(false)->after('activo');
            if (!in_array('force_password_change', $cols)) $t->boolean('force_password_change')->default(false)->after('es_superadmin');
            if (!in_array('last_login_at', $cols))         $t->timestamp('last_login_at')->nullable()->after('force_password_change');
            if (!in_array('last_login_ip', $cols))         $t->string('last_login_ip', 45)->nullable()->after('last_login_at');
            if (!in_array('remember_token', $cols))        $t->rememberToken();
            if (!in_array('created_at', $cols) || !in_array('updated_at', $cols)) $t->timestamps();
        });

        // 3) Asegura el índice único de email SÓLO si no existe ya (por nombre o por columna)
        if (!$this->uniqueIndexExists($conn, $table, 'usuario_administrativos_email_unique', 'email')) {
            Schema::connection($conn)->table($table, function (Blueprint $t) {
                $t->unique('email', 'usuario_administrativos_email_unique');
            });
        }
    }

    public function down(): void
    {
        $conn = $this->connection;
        $table = 'usuario_administrativos';

        // Bajada conservadora: elimina ÚNICAMENTE el índice si existe.
        if (Schema::connection($conn)->hasTable($table)) {
            if ($this->uniqueIndexExists($conn, $table, 'usuario_administrativos_email_unique', 'email')) {
                Schema::connection($conn)->table($table, function (Blueprint $t) {
                    $t->dropUnique('usuario_administrativos_email_unique');
                });
            }
            // Si esta migración originalmente CREABA la tabla
            // y deseas revertirla completa, descomenta:
            // Schema::connection($conn)->dropIfExists($table);
        }
    }

    /**
     * Verifica si existe un índice único por nombre o un índice único sobre la columna dada.
     */
    private function uniqueIndexExists(string $conn, string $table, ?string $indexName = null, ?string $column = null): bool
    {
        try {
            $db = DB::connection($conn)->getDatabaseName();

            $q = DB::connection($conn)->table('information_schema.statistics')
                ->where('TABLE_SCHEMA', $db)
                ->where('TABLE_NAME', $table)
                ->where('NON_UNIQUE', 0); // únicos

            if ($indexName) {
                $q2 = (clone $q)->where('INDEX_NAME', $indexName)->exists();
                if ($q2) return true;
            }
            if ($column) {
                $q3 = (clone $q)->where('COLUMN_NAME', $column)->exists();
                if ($q3) return true;
            }
        } catch (\Throwable $e) {
            // Si algo falla al consultar INFORMATION_SCHEMA, no bloqueamos la migración
        }
        return false;
        }
};
