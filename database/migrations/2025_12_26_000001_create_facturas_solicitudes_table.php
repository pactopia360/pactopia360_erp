<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn = 'mysql_clientes';
        $table = 'facturas_solicitudes';

        // 1) Si ya existe, NO truena. Ajusta mínimos “seguros” (índices/unique) si aplica.
        if (Schema::connection($conn)->hasTable($table)) {
            // Asegurar índices/unique sin romper si ya están.
            Schema::connection($conn)->table($table, function (Blueprint $t) use ($conn, $table) {
                // Nota: Laravel no expone "hasIndex" fácil; evitamos duplicados con try/catch implícito
                // mediante comprobación de columnas y creación de índices con nombres explícitos,
                // pero si ya existen, MySQL puede lanzar error. Para evitarlo, solo creamos el UNIQUE
                // si NO existe como constraint (no hay API directa), así que lo hacemos por “best effort”
                // usando hasColumn y un nombre fijo; si ya existe, lo ignoramos con un enfoque seguro
                // (ver bloque try/catch externo).
            });

            // Crear el UNIQUE si no existe (best effort).
            // Si ya existe, MySQL lanzará error 1061/1022; lo atrapamos con un try/catch.
            try {
                Schema::connection($conn)->table($table, function (Blueprint $t) {
                    $t->unique(['account_id', 'period'], 'ux_facturas_account_period');
                });
            } catch (\Throwable $e) {
                // Si ya existe, no hacemos nada. No interrumpimos migraciones.
            }

            return;
        }

        // 2) Si NO existe, crear tabla
        Schema::connection($conn)->create($table, function (Blueprint $t) {
            $t->bigIncrements('id');

            // Multi-tenant / Account
            $t->uuid('account_id')->index();

            // Periodo asociado (tu lógica usa YYYY-MM)
            $t->string('period', 7)->nullable()->index(); // '2025-12'

            // Quién solicitó (usuario cliente)
            $t->uuid('requested_by')->nullable()->index();

            // Estado del flujo
            // requested -> admin_notified -> in_progress -> ready -> delivered / rejected / cancelled
            $t->string('status', 30)->default('requested')->index();

            // Datos del admin (quién atendió)
            $t->uuid('handled_by')->nullable()->index(); // id admin (si lo manejas como uuid); si no, cámbialo a unsignedBigInteger
            $t->timestamp('handled_at')->nullable();

            // ZIP que sube el admin (ruta en storage)
            $t->string('zip_path', 500)->nullable();
            $t->string('zip_name', 191)->nullable();
            $t->unsignedBigInteger('zip_size')->nullable();

            // Metadatos útiles
            $t->string('invoice_uuid', 64)->nullable()->index(); // UUID CFDI si lo guardas
            $t->string('invoice_folio', 64)->nullable()->index(); // folio interno opcional
            $t->text('notes')->nullable();

            $t->json('meta')->nullable();

            // Auditoría
            $t->timestamp('requested_at')->nullable();
            $t->timestamp('ready_at')->nullable();
            $t->timestamp('downloaded_at')->nullable();
            $t->unsignedInteger('download_count')->default(0);

            $t->timestamps();

            // Evita que el cliente solicite 2 veces el mismo periodo (si aplica)
            $t->unique(['account_id', 'period'], 'ux_facturas_account_period');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('facturas_solicitudes');
    }
};
