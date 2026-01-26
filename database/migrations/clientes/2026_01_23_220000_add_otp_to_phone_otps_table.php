<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Esta migración vive en la BD de CLIENTES.
     * Importante: NO usar Schema::... sin connection(), porque tomaría la conexión default (mysql).
     */
    private string $conn = 'mysql_clientes';

    public function up(): void
    {
        // Ajusta el nombre si tu tabla se llama diferente
        if (!Schema::connection($this->conn)->hasTable('phone_otps')) {
            return;
        }

        Schema::connection($this->conn)->table('phone_otps', function (Blueprint $table) {
            if (!Schema::connection($this->conn)->hasColumn('phone_otps', 'otp')) {
                // Mantén compatible con el insert actual (otp duplicado de code)
                $table->string('otp', 16)->nullable()->after('code');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::connection($this->conn)->hasTable('phone_otps')) {
            return;
        }

        Schema::connection($this->conn)->table('phone_otps', function (Blueprint $table) {
            if (Schema::connection($this->conn)->hasColumn('phone_otps', 'otp')) {
                $table->dropColumn('otp');
            }
        });
    }
};
