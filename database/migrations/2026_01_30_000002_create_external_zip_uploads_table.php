<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $conn  = 'mysql_clientes';
        $table = 'external_fiel_uploads';

        // ✅ Si ya existe, NO intentamos crearla (evita crash en producción)
        if (Schema::connection($conn)->hasTable($table)) {

            // ✅ Completa columnas mínimas usadas por el INSERT actual (sin romper si ya existen)
            Schema::connection($conn)->table($table, function (Blueprint $t) use ($conn, $table) {

                $has = fn(string $col) => Schema::connection($conn)->hasColumn($table, $col);

                if (!$has('account_id'))     $t->unsignedBigInteger('account_id')->index();
                if (!$has('rfc'))           $t->string('rfc', 13)->nullable()->index();
                if (!$has('email_externo')) $t->string('email_externo', 191)->nullable();
                if (!$has('reference'))     $t->string('reference', 120)->nullable();
                if (!$has('token'))         $t->string('token', 64)->unique();
                if (!$has('file_path'))     $t->string('file_path', 191)->nullable();
                if (!$has('file_name'))     $t->string('file_name', 191)->nullable();
                if (!$has('file_size'))     $t->unsignedBigInteger('file_size')->nullable();
                if (!$has('mime'))          $t->string('mime', 120)->nullable();
                if (!$has('fiel_password')) $t->text('fiel_password')->nullable();
                if (!$has('status'))        $t->string('status', 20)->default('invited')->index();
                if (!$has('uploaded_at'))   $t->timestamp('uploaded_at')->nullable();

                if (!$has('created_at')) $t->timestamp('created_at')->nullable();
                if (!$has('updated_at')) $t->timestamp('updated_at')->nullable();
            });

            return;
        }

        // ✅ Si NO existe, crearla completa
        Schema::connection($conn)->create($table, function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('account_id')->index();

            $table->string('rfc', 13)->nullable()->index();
            $table->string('email_externo', 191)->nullable();
            $table->string('reference', 120)->nullable();

            $table->string('token', 64)->unique();

            $table->string('file_path', 191)->nullable();
            $table->string('file_name', 191)->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('mime', 120)->nullable();

            $table->text('fiel_password')->nullable();

            $table->string('status', 20)->default('invited')->index();
            $table->timestamp('uploaded_at')->nullable();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->dropIfExists('external_fiel_uploads');
    }
};
