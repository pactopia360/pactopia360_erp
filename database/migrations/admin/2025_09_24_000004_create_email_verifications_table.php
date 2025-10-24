<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Importante: pública y SIN tipo
    public $connection = 'mysql_admin';

    public function up(): void
    {
        // Si la tabla YA existe, solo aseguramos columnas/índices
        if (Schema::connection($this->connection)->hasTable('email_verifications')) {
            Schema::connection($this->connection)->table('email_verifications', function (Blueprint $table) {
                // account_id
                if (!Schema::connection($this->connection)->hasColumn('email_verifications', 'account_id')) {
                    $table->unsignedBigInteger('account_id')->after('id');
                    $table->index('account_id', 'emailverif_account_id_idx');
                }
                // email
                if (!Schema::connection($this->connection)->hasColumn('email_verifications', 'email')) {
                    $table->string('email', 150)->after('account_id');
                    $table->index('email', 'emailverif_email_idx');
                }
                // token
                if (!Schema::connection($this->connection)->hasColumn('email_verifications', 'token')) {
                    $table->string('token', 80)->after('email');
                    $table->unique('token', 'emailverif_token_uniq');
                }
                // expires_at
                if (!Schema::connection($this->connection)->hasColumn('email_verifications', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('token');
                    $table->index('expires_at', 'emailverif_expires_idx');
                }
                // timestamps
                if (!Schema::connection($this->connection)->hasColumn('email_verifications', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->after('expires_at');
                }
                if (!Schema::connection($this->connection)->hasColumn('email_verifications', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }

                // FK (si tu MariaDB/FKs están OK; si no, puedes comentarlo)
                try {
                    $table->foreign('account_id')
                        ->references('id')->on('accounts')
                        ->onUpdate('cascade')->onDelete('cascade');
                } catch (\Throwable $e) {
                    // Ignoramos si ya existe o si el motor no permite crearla aquí
                }
            });

            // Listo: no creamos de nuevo
            return;
        }

        // Si NO existía, crearla completa
        Schema::connection($this->connection)->create('email_verifications', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->string('email', 150);
            $table->string('token', 80);
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index('account_id', 'emailverif_account_id_idx');
            $table->index('email', 'emailverif_email_idx');
            $table->unique('token', 'emailverif_token_uniq');
            $table->index('expires_at', 'emailverif_expires_idx');

            $table->foreign('account_id')
                ->references('id')->on('accounts')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Por seguridad, NO borramos la tabla (puede existir de antes).
        // Si necesitas reversa estricta, cámbialo a dropIfExists bajo tu propio riesgo.
        // Schema::connection($this->connection)->dropIfExists('email_verifications');
    }
};
