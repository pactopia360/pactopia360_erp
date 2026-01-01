<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Usamos la conexión de clientes.
     */
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        $conn = $this->connection;

        // 1) Si NO existe, crear con esquema "nuevo" (archivos + CFDI opcional)
        if (!Schema::connection($conn)->hasTable('sat_vault_files')) {
            Schema::connection($conn)->create('sat_vault_files', function (Blueprint $table) {
                $table->bigIncrements('id');

                $table->char('cuenta_id', 36)->index();

                // ==== Campos "archivo" (lo que tu controller está intentando guardar) ====
                $table->string('source', 50)->nullable()->index();      // ej: sat_download
                $table->string('source_id', 64)->nullable()->index();   // ej: id sat_download
                $table->string('rfc', 20)->nullable()->index();         // rfc “principal” para agrupar
                $table->string('filename', 255)->nullable();
                $table->string('path', 500)->nullable();                // ruta relativa dentro del disk
                $table->string('disk', 50)->nullable();                 // private|vault|sat_zip|etc
                $table->unsignedBigInteger('bytes')->default(0);        // tamaño real del archivo
                $table->string('mime', 120)->nullable();
                $table->char('created_by', 36)->nullable()->index();    // user id (uuid/char36)

                // ==== Campos CFDI opcionales (para “vista rápida” / indexado futuro) ====
                $table->char('uuid', 36)->nullable()->index();
                $table->date('fecha_emision')->nullable()->index();
                $table->string('tipo', 20)->default('emitidos')->index();     // emitidos|recibidos|zip
                $table->string('rfc_emisor', 20)->nullable()->index();
                $table->string('razon_social', 255)->nullable();
                $table->decimal('subtotal', 15, 2)->default(0);
                $table->decimal('iva', 15, 2)->default(0);
                $table->decimal('total', 15, 2)->default(0);

                // Compat/legacy: si antes usabas size_bytes
                $table->unsignedBigInteger('size_bytes')->nullable();

                $table->timestamps();

                // Evitar duplicados obvios si reintentas guardar el mismo path
                $table->unique(['cuenta_id', 'disk', 'path'], 'uniq_vault_file_path');
            });

            return;
        }

        // 2) Si YA existe: ALTER seguro (agregar columnas que falten)
        Schema::connection($conn)->table('sat_vault_files', function (Blueprint $table) use ($conn) {
            // helper inline para checar columnas sin romper
            $has = fn(string $col) => Schema::connection($conn)->hasColumn('sat_vault_files', $col);

            // ==== Campos archivo ====
            if (!$has('source'))     $table->string('source', 50)->nullable()->index();
            if (!$has('source_id'))  $table->string('source_id', 64)->nullable()->index();

            if (!$has('rfc'))        $table->string('rfc', 20)->nullable()->index();
            if (!$has('filename'))   $table->string('filename', 255)->nullable();
            if (!$has('path'))       $table->string('path', 500)->nullable();
            if (!$has('disk'))       $table->string('disk', 50)->nullable();
            if (!$has('bytes'))      $table->unsignedBigInteger('bytes')->default(0);
            if (!$has('mime'))       $table->string('mime', 120)->nullable();
            if (!$has('created_by')) $table->char('created_by', 36)->nullable()->index();

            // ==== Campos CFDI opcionales ====
            if (!$has('uuid'))         $table->char('uuid', 36)->nullable()->index();
            if (!$has('fecha_emision'))$table->date('fecha_emision')->nullable()->index();
            if (!$has('tipo'))         $table->string('tipo', 20)->default('emitidos')->index();
            if (!$has('rfc_emisor'))   $table->string('rfc_emisor', 20)->nullable()->index();
            if (!$has('razon_social')) $table->string('razon_social', 255)->nullable();
            if (!$has('subtotal'))     $table->decimal('subtotal', 15, 2)->default(0);
            if (!$has('iva'))          $table->decimal('iva', 15, 2)->default(0);
            if (!$has('total'))        $table->decimal('total', 15, 2)->default(0);

            // Compat/legacy
            if (!$has('size_bytes'))   $table->unsignedBigInteger('size_bytes')->nullable();
        });

        // 3) Índice único (si no existe). MySQL no tiene "if not exists" para índices en Schema builder,
        // así que lo intentamos en try/catch.
        try {
            Schema::connection($conn)->table('sat_vault_files', function (Blueprint $table) {
                $table->unique(['cuenta_id', 'disk', 'path'], 'uniq_vault_file_path');
            });
        } catch (\Throwable $e) {
            // ya existe o no aplica; no rompemos migración
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('sat_vault_files');
    }
};
