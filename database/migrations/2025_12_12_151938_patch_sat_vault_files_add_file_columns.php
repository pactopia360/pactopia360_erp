<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->connection;

        if (!Schema::connection($conn)->hasTable('sat_vault_files')) {
            return;
        }

        Schema::connection($conn)->table('sat_vault_files', function (Blueprint $table) use ($conn) {
            $has = fn(string $col) => Schema::connection($conn)->hasColumn('sat_vault_files', $col);

            // ==== Campos que tu controller usa ====
            if (!$has('source'))     $table->string('source', 50)->nullable()->index();
            if (!$has('source_id'))  $table->string('source_id', 64)->nullable()->index();
            if (!$has('rfc'))        $table->string('rfc', 20)->nullable()->index();
            if (!$has('filename'))   $table->string('filename', 255)->nullable();
            if (!$has('path'))       $table->string('path', 500)->nullable();
            if (!$has('disk'))       $table->string('disk', 50)->nullable();
            if (!$has('bytes'))      $table->unsignedBigInteger('bytes')->default(0);
            if (!$has('mime'))       $table->string('mime', 120)->nullable();
            if (!$has('created_by')) $table->char('created_by', 36)->nullable()->index();

            // ==== Opcionales CFDI (por compatibilidad) ====
            if (!$has('tipo'))          $table->string('tipo', 20)->nullable()->index();
            if (!$has('uuid'))          $table->char('uuid', 36)->nullable()->index();
            if (!$has('fecha_emision')) $table->date('fecha_emision')->nullable()->index();
            if (!$has('rfc_emisor'))    $table->string('rfc_emisor', 20)->nullable()->index();
            if (!$has('razon_social'))  $table->string('razon_social', 255)->nullable();
            if (!$has('subtotal'))      $table->decimal('subtotal', 15, 2)->default(0);
            if (!$has('iva'))           $table->decimal('iva', 15, 2)->default(0);
            if (!$has('total'))         $table->decimal('total', 15, 2)->default(0);
        });

        // Índice único (si no existe)
        try {
            Schema::connection($conn)->table('sat_vault_files', function (Blueprint $table) {
                $table->unique(['cuenta_id', 'disk', 'path'], 'uniq_vault_file_path');
            });
        } catch (\Throwable $e) {
            // ya existía
        }
    }

    public function down(): void
    {
        // No bajamos columnas para no perder históricos
    }
};
