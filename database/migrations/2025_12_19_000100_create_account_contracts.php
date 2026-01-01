<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        /**
         * ✅ SOLO mysql_admin.
         * Si ejecutas: php artisan migrate --database=mysql_clientes
         * esta migración NO debe tocar nada.
         */
        $runnerConn = Schema::getConnection()->getName();
        if ($runnerConn !== 'mysql_admin') {
            return;
        }

        $conn = 'mysql_admin';

        if (!Schema::connection($conn)->hasTable('account_contracts')) {
            Schema::connection($conn)->create('account_contracts', function (Blueprint $t) {
                $t->bigIncrements('id');

                // Tu cuenta en UI se ve como UUID string (ej: 565d...), por eso string(36)
                $t->string('account_id', 36)->index();

                $t->string('code', 50)->default('acceptance')->index();   // acceptance, etc.
                $t->string('title', 160)->default('Contrato de aceptación de servicio');
                $t->string('version', 30)->default('v1');

                $t->enum('status', ['pending', 'signed'])->default('pending')->index();

                // Firma
                $t->timestamp('signed_at')->nullable()->index();
                $t->string('signed_by_user_id', 36)->nullable()->index();
                $t->string('signed_name', 190)->nullable();
                $t->string('signed_email', 190)->nullable();

                // Firma en base64 (PNG) + hash
                $t->longText('signature_png_base64')->nullable();
                $t->string('signature_hash', 64)->nullable()->index();

                // PDF firmado (ruta storage)
                $t->string('signed_pdf_path', 255)->nullable();

                $t->timestamps();

                $t->unique(['account_id', 'code', 'version'], 'uq_account_contract_version');
            });
        }
    }

    public function down(): void
    {
        $runnerConn = Schema::getConnection()->getName();
        if ($runnerConn !== 'mysql_admin') {
            return;
        }

        Schema::connection('mysql_admin')->dropIfExists('account_contracts');
    }
};
