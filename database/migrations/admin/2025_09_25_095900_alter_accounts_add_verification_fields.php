<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public $connection = 'mysql_admin';

    /** Helper: verifica si existe un índice por nombre */
    private function indexExists(string $table, string $indexName): bool
    {
        $rows = DB::connection($this->connection)
            ->select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$indexName]);
        return !empty($rows);
    }

    public function up(): void
    {
        // 1) Asegurar columnas
        Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
            $conn = Schema::connection('mysql_admin');

            if (!$conn->hasColumn('accounts', 'email_verified_at')) {
                $table->timestamp('email_verified_at')->nullable()->after('email');
            }
            if (!$conn->hasColumn('accounts', 'phone_verified_at')) {
                $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
            }
            if (!$conn->hasColumn('accounts', 'is_blocked')) {
                $table->boolean('is_blocked')->default(0)->after('phone');
            }
            if (!$conn->hasColumn('accounts', 'plan')) {
                $table->string('plan', 20)->default('FREE')->after('phone');
            }
            if (!$conn->hasColumn('accounts', 'meta')) {
                $table->json('meta')->nullable()->after('is_blocked');
            }
        });

        // 2) Asegurar índices (solo si no existen)
        if (!$this->indexExists('accounts', 'accounts_email_verified_at_index')) {
            Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
                $table->index('email_verified_at');
            });
        }
        if (!$this->indexExists('accounts', 'accounts_phone_verified_at_index')) {
            Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
                $table->index('phone_verified_at');
            });
        }
        if (!$this->indexExists('accounts', 'accounts_plan_index')) {
            Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
                $table->index('plan');
            });
        }
    }

    public function down(): void
    {
        // Conservador: solo eliminamos columnas si existen.
        Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
            $conn = Schema::connection('mysql_admin');

            foreach (['email_verified_at','phone_verified_at','is_blocked','plan','meta'] as $col) {
                if ($conn->hasColumn('accounts', $col)) {
                    $table->dropColumn($col);
                }
            }
        });

        // No tocamos índices en down() para evitar conflictos con índices preexistentes.
    }
};
