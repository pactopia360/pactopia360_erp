<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    /** ¡OJO! Debe ser pública y SIN tipo para compatibilidad */
    public $connection = 'mysql_admin';

    public function up(): void
    {
        // Si ya existe, NO intentes crear; sólo asegúrate de columnas mínimas
        if (Schema::connection($this->connection)->hasTable('accounts')) {
            Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
                // agrega sólo si faltan (defensivo)
                if (!Schema::connection($this->connection)->hasColumn('accounts', 'plan')) {
                    $table->string('plan', 32)->default('FREE')->after('phone');
                }
                if (!Schema::connection($this->connection)->hasColumn('accounts', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable()->after('email');
                }
                if (!Schema::connection($this->connection)->hasColumn('accounts', 'phone_verified_at')) {
                    $table->timestamp('phone_verified_at')->nullable()->after('email_verified_at');
                }
                if (!Schema::connection($this->connection)->hasColumn('accounts', 'is_blocked')) {
                    $table->boolean('is_blocked')->default(0)->after('phone');
                }
                if (!Schema::connection($this->connection)->hasColumn('accounts', 'meta')) {
                    $table->json('meta')->nullable()->after('is_blocked');
                }

                $table->index(['plan']);
                $table->index(['email_verified_at']);
                $table->index(['phone_verified_at']);
            });
            return; // ¡listo! marcamos esta migration como ejecutada sin crear tabla
        }

        // Si no existiera, entonces sí crear
        Schema::connection($this->connection)->create('accounts', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('name', 150);
            $table->string('email', 150)->nullable();
            $table->string('rfc', 20);
            $table->string('phone', 30)->nullable();
            $table->string('plan', 32)->default('FREE');
            $table->timestamp('email_verified_at')->nullable();
            $table->timestamp('phone_verified_at')->nullable();
            $table->boolean('is_blocked')->default(0);
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->unique('rfc');
            $table->index(['plan']);
            $table->index(['email_verified_at']);
            $table->index(['phone_verified_at']);
        });
    }

    public function down(): void
    {
        // Por seguridad NO borramos la tabla si ya existía previo a esta migration.
        if (Schema::connection($this->connection)->hasTable('accounts')) {
            Schema::connection($this->connection)->table('accounts', function (Blueprint $table) {
                foreach (['plan','email_verified_at','phone_verified_at','is_blocked','meta'] as $col) {
                    if (Schema::connection($this->connection)->hasColumn('accounts', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
