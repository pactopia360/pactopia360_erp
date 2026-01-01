<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        if (!Schema::connection($this->conn)->hasTable('billing_email_logs')) return;

        $cols = Schema::connection($this->conn)->getColumnListing('billing_email_logs');
        $lc   = array_map('strtolower', $cols);
        $has  = fn(string $c) => in_array(strtolower($c), $lc, true);

        // Helper: ejecuta SQL, pero NO rompe si el error es "esperable" (duplicados / ya existe)
        $execSafe = function (string $sql): void {
            try {
                DB::connection($this->conn)->statement($sql);
            } catch (\Throwable $e) {
                $msg = strtolower($e->getMessage());

                // Duplicados / ya existe (MySQL)
                $ignorable =
                    str_contains($msg, 'duplicate key name') ||     // 1061
                    str_contains($msg, 'already exists') ||         // 1050/1060 variaciones
                    str_contains($msg, 'duplicate column name') ||  // 1060
                    str_contains($msg, 'duplicate') && str_contains($msg, 'index') ||
                    str_contains($msg, 'can\'t create') && str_contains($msg, 'exists');

                if ($ignorable) return;

                // Si no es ignorable, sí tronamos (error real)
                throw $e;
            }
        };

        // Helper: verifica si existe un índice por NOMBRE en la tabla
        $indexExists = function (string $indexName): bool {
            try {
                $dbName = (string) DB::connection($this->conn)->getDatabaseName();

                $rows = DB::connection($this->conn)->select(
                    "SELECT 1
                     FROM information_schema.STATISTICS
                     WHERE TABLE_SCHEMA = ?
                       AND TABLE_NAME = 'billing_email_logs'
                       AND INDEX_NAME = ?
                     LIMIT 1",
                    [$dbName, $indexName]
                );

                return !empty($rows);
            } catch (\Throwable $e) {
                // Si no podemos verificar, asumimos que existe para evitar duplicar y tronar.
                return true;
            }
        };

        // Helper: crea índice solo si NO existe por nombre
        $ensureIndex = function (string $indexName, string $sqlCreateIndex) use ($indexExists, $execSafe): void {
            if ($indexExists($indexName)) return;
            $execSafe($sqlCreateIndex);
        };

        // 1) Asegurar email_id (tracking)
        // NOTA: en tu tabla ACTUAL ya existe email_id CHAR(36) UNIQUE,
        // así que esto normalmente no hará nada. Lo dejamos safe por compat.
        if (!$has('email_id')) {
            $execSafe("ALTER TABLE billing_email_logs ADD COLUMN email_id VARCHAR(64) NULL AFTER id");
        }
        // Crea índice solo si no existe (si ya hay UNIQUE por email_id, normalmente no lo necesitas,
        // pero si tu versión vieja no tenía unique, este ayuda).
        $ensureIndex(
            'billing_email_logs_email_id_idx',
            "CREATE INDEX billing_email_logs_email_id_idx ON billing_email_logs(email_id)"
        );

        // 2) account_id: tu DB antes lo exigía; lo hacemos NULLABLE y garantizamos índice.
        if ($has('account_id')) {
            // Si ya es nullable, MySQL no debería tronar; si trona por tipo, execSafe decide.
            $execSafe("ALTER TABLE billing_email_logs MODIFY account_id VARCHAR(64) NULL");
        } else {
            $execSafe("ALTER TABLE billing_email_logs ADD COLUMN account_id VARCHAR(64) NULL AFTER statement_id");
        }

        // ESTE era el que te estaba tronando: ahora se crea solo si no existe.
        $ensureIndex(
            'billing_email_logs_account_id_idx',
            "CREATE INDEX billing_email_logs_account_id_idx ON billing_email_logs(account_id)"
        );

        // 3) Period opcional (si te sirve para queries rápidas)
        if (!$has('period')) {
            $execSafe("ALTER TABLE billing_email_logs ADD COLUMN period VARCHAR(7) NULL AFTER account_id");
        }
        $ensureIndex(
            'billing_email_logs_period_idx',
            "CREATE INDEX billing_email_logs_period_idx ON billing_email_logs(period)"
        );

        // 4) Backfill email_id si está NULL (para registros viejos)
        if ($has('email_id')) {
            $rows = DB::connection($this->conn)->table('billing_email_logs')
                ->whereNull('email_id')
                ->limit(5000)
                ->get(['id']);

            foreach ($rows as $r) {
                DB::connection($this->conn)->table('billing_email_logs')
                    ->where('id', (int) $r->id)
                    ->update([
                        'email_id'    => (string) \Illuminate\Support\Str::ulid(),
                        'updated_at'  => now(),
                    ]);
            }
        }

        // 5) Si existe payload, y account_id/period están vacíos, intenta rellenar
        if ($has('payload') && $has('account_id')) {
            $rows2 = DB::connection($this->conn)->table('billing_email_logs')
                ->where(function ($q) {
                    $q->whereNull('account_id')->orWhere('account_id', '');
                })
                ->limit(2000)
                ->get(['id', 'payload']);

            foreach ($rows2 as $r) {
                $payload = [];
                try {
                    $payload = is_string($r->payload) ? (json_decode($r->payload, true) ?: []) : [];
                } catch (\Throwable $e) {
                    $payload = [];
                }

                $aid = (string) ($payload['account_id'] ?? '');
                $per = (string) ($payload['period'] ?? '');

                if ($aid !== '' || $per !== '') {
                    DB::connection($this->conn)->table('billing_email_logs')
                        ->where('id', (int) $r->id)
                        ->update([
                            'account_id' => $aid !== '' ? $aid : null,
                            'period'     => preg_match('/^\d{4}\-\d{2}$/', $per) ? $per : null,
                            'updated_at' => now(),
                        ]);
                }
            }
        }
    }

    public function down(): void
    {
        // No revertimos por seguridad.
    }
};
