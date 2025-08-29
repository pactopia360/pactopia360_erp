<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (!$schema->hasTable('clientes')) {
            $schema->create('clientes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('razon_social', 255);
                $table->string('nombre', 150)->nullable(); // alias corto/comercial
                $table->string('rfc', 13)->unique();
                $table->string('email', 190)->index();
                $table->string('telefono', 30)->nullable();
                $table->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete();
                $table->string('plan', 30)->nullable(); // fallback (compat HomeController)
                $table->boolean('activo')->default(true);
                $table->string('codigo', 40)->unique()->comment('código único de cliente');
                $table->timestamp('ultimo_acceso_at')->nullable();
                $table->timestamps();
            });
        } else {
            $schema->table('clientes', function (Blueprint $table) {
                $ensure = function(string $col, callable $def){
                    if (!Schema::connection($this->connection)->hasColumn('clientes', $col)) { $def(); }
                };

                $ensure('nombre', fn()=> $table->string('nombre',150)->nullable()->after('razon_social'));
                $ensure('rfc', fn()=> $table->string('rfc',13)->unique()->after('nombre'));
                $ensure('email', fn()=> $table->string('email',190)->index()->after('rfc'));
                $ensure('telefono', fn()=> $table->string('telefono',30)->nullable()->after('email'));
                $ensure('plan_id', fn()=> $table->foreignId('plan_id')->nullable()->constrained('planes')->nullOnDelete()->after('telefono'));
                $ensure('plan', fn()=> $table->string('plan',30)->nullable()->after('plan_id'));
                $ensure('activo', fn()=> $table->boolean('activo')->default(true)->after('plan'));
                $ensure('codigo', fn()=> $table->string('codigo',40)->unique()->after('activo'));
                $ensure('ultimo_acceso_at', fn()=> $table->timestamp('ultimo_acceso_at')->nullable()->after('codigo'));
            });
        }
    }

    public function down(): void {}
};
