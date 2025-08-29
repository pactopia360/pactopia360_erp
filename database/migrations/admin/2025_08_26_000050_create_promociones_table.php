<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (!$schema->hasTable('promociones')) {
            $schema->create('promociones', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('plan_id')->constrained('planes')->cascadeOnDelete();
                $table->string('titulo', 100);
                $table->string('tipo', 30)->default('porcentaje'); // porcentaje|descuento_fijo
                $table->decimal('valor', 10, 2)->default(0);       // % o $ según tipo
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_fin')->nullable();
                $table->string('codigo_cupon', 50)->nullable()->unique();
                $table->integer('uso_maximo')->default(0); // 0 = ilimitado
                $table->integer('usos_actuales')->default(0);
                $table->boolean('activa')->default(true);
                $table->timestamps();
            });
        } else {
            $schema->table('promociones', function (Blueprint $table) {
                $add = function(string $col, callable $def){
                    if (!Schema::connection($this->connection)->hasColumn('promociones', $col)) { $def(); }
                };
                $add('plan_id', fn()=> $table->foreignId('plan_id')->constrained('planes')->cascadeOnDelete());
                $add('tipo', fn()=> $table->string('tipo',30)->default('porcentaje'));
                $add('valor', fn()=> $table->decimal('valor',10,2)->default(0));
                $add('codigo_cupon', fn()=> $table->string('codigo_cupon',50)->nullable()->unique());
                $add('uso_maximo', fn()=> $table->integer('uso_maximo')->default(0));
                $add('usos_actuales', fn()=> $table->integer('usos_actuales')->default(0));
                $add('activa', fn()=> $table->boolean('activa')->default(true));
            });
        }
    }

    public function down(): void {}
};
