<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_admin';

    public function up(): void
    {
        // ---------- PAGOS ----------
        Schema::connection($this->conn)->table('pagos', function (Blueprint $t) {
            // Asegura columnas presentes
            if (!Schema::connection($this->conn)->hasColumn('pagos','cuenta_id')) {
                $t->char('cuenta_id', 36)->after('id');
            }
            if (!Schema::connection($this->conn)->hasColumn('pagos','fecha')) {
                $t->dateTime('fecha')->nullable()->after('monto');
            }
            if (!Schema::connection($this->conn)->hasColumn('pagos','estatus')) {
                $t->enum('estatus',['pendiente','pagado','fallido'])->default('pendiente')->after('metodo');
            }

            // Campos legado de compatibilidad (que causan errores en seeders viejos)
            if (Schema::connection($this->conn)->hasColumn('pagos','cliente_id')) {
                // hazlo NULL y sin FK para que no bloquee (no lo borramos por si hay código viejo)
                $t->unsignedBigInteger('cliente_id')->nullable()->change();
            }
        });

        // Quitar FK vieja de cliente_id si existiese y dejarla suelta
        try {
            DB::connection($this->conn)->statement('ALTER TABLE pagos DROP FOREIGN KEY pagos_cliente_id_foreign');
        } catch (\Throwable $e) {}

        // Normaliza tipo y FK de cuenta_id (puede truncar si hay basura; limpiamos primero)
        // 1) Forzar longitud a 36 y NULL donde no haga match
        DB::connection($this->conn)->statement("UPDATE pagos SET cuenta_id = NULL WHERE CHAR_LENGTH(cuenta_id) NOT IN (36)");
        DB::connection($this->conn)->statement("ALTER TABLE pagos MODIFY cuenta_id CHAR(36) NULL");

        // 2) Borra FK previa si tuviera otro nombre
        try { DB::connection($this->conn)->statement('ALTER TABLE pagos DROP FOREIGN KEY pagos_cuenta_id_foreign'); } catch (\Throwable $e) {}

        // 3) Re-crear FK correcta (cuentas.id es CHAR(36))
        try {
            DB::connection($this->conn)->statement(
                'ALTER TABLE pagos ADD CONSTRAINT pagos_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE'
            );
        } catch (\Throwable $e) {}

        // ---------- ESTADOS_CUENTA ----------
        // Asegura la tabla
        if (Schema::connection($this->conn)->hasTable('estados_cuenta')) {
            // 1) Normaliza tipos
            // periodo debe ser DATE (no VARCHAR/CHAR), id autoincrement BIGINT UNSIGNED PK
            // Convertimos periodo si no es DATE
            $col = collect(DB::connection($this->conn)->select("SHOW COLUMNS FROM estados_cuenta"))->firstWhere('Field','periodo');
            if ($col && stripos($col->Type,'date') === false) {
                // si está como texto, intentar convertirlo de 'YYYY-MM-01' a DATE
                DB::connection($this->conn)->statement("UPDATE estados_cuenta SET periodo = STR_TO_DATE(periodo,'%Y-%m-%d') WHERE periodo REGEXP '^[0-9]{4}-[0-9]{2}-[0-9]{2}$'");
                DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta MODIFY periodo DATE NOT NULL");
            } else {
                // asegúralo por si viene nullable/otro length
                DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta MODIFY periodo DATE NOT NULL");
            }

            // cuenta_id a CHAR(36)
            $col = collect(DB::connection($this->conn)->select("SHOW COLUMNS FROM estados_cuenta"))->firstWhere('Field','cuenta_id');
            if ($col && stripos($col->Type,'char(36)') === false) {
                DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta MODIFY cuenta_id CHAR(36) NOT NULL");
            }

            // 2) Asegura PK autoincrement para id
            // Detecta si id NO es auto_increment
            $status = DB::connection($this->conn)->select("SHOW KEYS FROM estados_cuenta WHERE Key_name = 'PRIMARY'");
            try {
                // Si ya hay PK en id pero sin autoincrement, lo forzamos
                DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta MODIFY id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT");
            } catch (\Throwable $e) {
                // Si no existe id, lo creamos
                try {
                    DB::connection($this->conn)->statement("ALTER TABLE estados_cuenta ADD COLUMN id BIGINT UNSIGNED NOT NULL PRIMARY KEY AUTO_INCREMENT FIRST");
                } catch (\Throwable $e2) {}
            }

            // 3) Índices útiles y FK
            try { DB::connection($this->conn)->statement("CREATE INDEX estados_cuenta_periodo_index ON estados_cuenta (periodo)"); } catch (\Throwable $e) {}
            try { DB::connection($this->conn)->statement("CREATE INDEX estados_cuenta_cuenta_id_index ON estados_cuenta (cuenta_id)"); } catch (\Throwable $e) {}

            // FK a cuentas(id)
            try { DB::connection($this->conn)->statement('ALTER TABLE estados_cuenta DROP FOREIGN KEY estados_cuenta_cuenta_id_foreign'); } catch (\Throwable $e) {}
            try {
                DB::connection($this->conn)->statement(
                    'ALTER TABLE estados_cuenta ADD CONSTRAINT estados_cuenta_cuenta_id_foreign FOREIGN KEY (cuenta_id) REFERENCES cuentas(id) ON DELETE CASCADE'
                );
            } catch (\Throwable $e) {}
        }

        // ---------- DATOS MÍNIMOS DE APOYO ----------
        // Si existe columna 'clave' en planes, asegura defaults para evitar el 1364
        if (Schema::connection($this->conn)->hasColumn('planes','clave')) {
            DB::connection($this->conn)->table('planes')->whereNull('clave')->update(['clave'=>'free']);
        }
    }

    public function down(): void
    {
        // No revertimos cambios estructurales (operación de saneo).
    }
};
