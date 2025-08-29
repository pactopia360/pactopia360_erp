<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (!$schema->hasTable('usuarios_admin')) {
            $schema->create('usuarios_admin', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre', 150);
                $table->string('email', 190)->unique();
                $table->string('password');
                $table->string('rol', 30)->default('usuario');   // superadmin|ventas|soporte|dev|conta
                $table->json('permisos')->nullable();            // permisos finos
                $table->boolean('activo')->default(true);
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
            });
        } else {
            $schema->table('usuarios_admin', function (Blueprint $table) {
                $add = function(string $col, callable $def){
                    if (!Schema::connection($this->connection)->hasColumn('usuarios_admin', $col)) { $def(); }
                };
                $add('rol', fn()=> $table->string('rol',30)->default('usuario')->after('password'));
                $add('permisos', fn()=> $table->json('permisos')->nullable()->after('rol'));
                $add('activo', fn()=> $table->boolean('activo')->default(true)->after('permisos'));
                $add('last_login_at', fn()=> $table->timestamp('last_login_at')->nullable()->after('activo'));
                $add('remember_token', fn()=> $table->rememberToken());
            });
        }
    }

    public function down(): void {}
};
