<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private string $conn = 'mysql_admin';

    public function up(): void
    {
        $sc = Schema::connection($this->conn);

        if (! $sc->hasTable('billing_statement_status_overrides')) {
            return;
        }

        if (! $sc->hasColumn('billing_statement_status_overrides', 'meta')) {
            $sc->table('billing_statement_status_overrides', function (Blueprint $table) {
                $table->json('meta')->nullable()->after('updated_by');
            });
        }

        if (! $sc->hasColumn('billing_statement_status_overrides', 'created_at')
            && ! $sc->hasColumn('billing_statement_status_overrides', 'updated_at')) {
            $sc->table('billing_statement_status_overrides', function (Blueprint $table) {
                $table->timestamps();
            });
        }

        DB::connection($this->conn)->table('billing_statement_status_overrides')
            ->whereNull('meta')
            ->update([
                'meta' => json_encode([
                    'pay_method'   => null,
                    'pay_provider' => null,
                    'pay_status'   => null,
                    'paid_at'      => null,
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            ]);
    }

    public function down(): void
    {
        $sc = Schema::connection($this->conn);

        if (! $sc->hasTable('billing_statement_status_overrides')) {
            return;
        }

        if ($sc->hasColumn('billing_statement_status_overrides', 'meta')) {
            $sc->table('billing_statement_status_overrides', function (Blueprint $table) {
                $table->dropColumn('meta');
            });
        }
    }
};