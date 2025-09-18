<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // OJO: sin typehints (para evitar el error de Laravel con propiedades tipadas).
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('usuario_administrativos')) {
            Schema::connection($this->connection)->create('usuario_administrativos', function (Blueprint $table) {
                $table->id();
                $table->string('nombre', 191);
                $table->string('email', 191)->unique();
                $table->string('password');
                $table->string('rol', 50)->default('admin');
                $table->boolean('activo')->default(true);
                $table->boolean('es_superadmin')->default(false);
                $table->boolean('force_password_change')->default(false);
                $table->timestamp('last_login_at')->nullable();
                $table->string('last_login_ip', 45)->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        } else {
            // Idempotente: agrega columnas faltantes si ya existía
            Schema::connection($this->connection)->table('usuario_administrativos', function (Blueprint $table) {
                $cols = Schema::connection($this->connection)->getColumnListing('usuario_administrativos');

                if (!in_array('rol', $cols))                   $table->string('rol', 50)->default('admin')->after('password');
                if (!in_array('activo', $cols))                $table->boolean('activo')->default(true)->after('rol');
                if (!in_array('es_superadmin', $cols))         $table->boolean('es_superadmin')->default(false)->after('activo');
                if (!in_array('force_password_change', $cols)) $table->boolean('force_password_change')->default(false)->after('es_superadmin');
                if (!in_array('last_login_at', $cols))         $table->timestamp('last_login_at')->nullable()->after('force_password_change');
                if (!in_array('last_login_ip', $cols))         $table->string('last_login_ip', 45)->nullable()->after('last_login_at');

                try { $table->unique('email', 'usuario_administrativos_email_unique'); } catch (\Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('usuario_administrativos');
    }
};
