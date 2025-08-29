<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('planes')) {
            Schema::connection($this->connection)->create('planes', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('clave', 40)->unique();     // ej: 'free', 'premium'
                $table->string('nombre', 100);
                $table->text('descripcion')->nullable();
                $table->decimal('precio_mensual', 10, 2)->default(0);
                $table->decimal('precio_anual', 10, 2)->default(0);
                $table->boolean('es_premium')->default(false);
                $table->boolean('activo')->default(true);
                // límites/beneficios base
                $table->integer('timbres_incluidos')->default(0);
                $table->integer('espacio_gb')->default(1);
                $table->json('opciones')->nullable();
                $table->timestamps();
            });
        } else {
            Schema::connection($this->connection)->table('planes', function (Blueprint $table) {
                foreach ([
                    ['clave','string',40],
                    ['nombre','string',100],
                    ['descripcion','text',null,true],
                    ['precio_mensual','decimal',[10,2]],
                    ['precio_anual','decimal',[10,2]],
                    ['es_premium','boolean',null,false],
                    ['activo','boolean',null,true],
                    ['timbres_incluidos','integer',null,0],
                    ['espacio_gb','integer',null,1],
                    ['opciones','json',null,true],
                ] as $c) {
                    [$name,$type,$len,$nullableOrDefault] = $c + [null,null,null,null];
                    if (!Schema::connection($this->connection)->hasColumn('planes', $name)) {
                        $col = match($type){
                            'string'  => $table->string($name, $len),
                            'decimal' => $table->decimal($name, $len[0], $len[1]),
                            'text'    => $table->text($name),
                            'boolean' => $table->boolean($name),
                            'integer' => $table->integer($name),
                            'json'    => $table->json($name),
                            default   => $table->string($name),
                        };
                        if ($nullableOrDefault === true) $col->nullable();
                        elseif (is_bool($nullableOrDefault)) $col->default($nullableOrDefault);
                        elseif (is_int($nullableOrDefault)) $col->default($nullableOrDefault);
                    }
                }
                // índice único para clave si hiciera falta
                try { $table->unique('clave'); } catch (\Throwable $e) {}
            });
        }
    }

    public function down(): void
    {
        // No se dropea en producción; deja vacío o habilita si lo necesitas.
        // Schema::connection($this->connection)->dropIfExists('planes');
    }
};
