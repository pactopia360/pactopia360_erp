<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (!$schema->hasTable('pagos')) {
            $schema->create('pagos', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
                $table->decimal('monto', 12, 2);
                $table->timestamp('fecha')->useCurrent()->index();
                $table->string('estado', 20)->default('pagado')->index(); // pagado|pendiente|fallido|cancelado
                $table->string('metodo_pago', 50)->nullable();
                $table->string('referencia', 100)->nullable();
                $table->timestamps();
            });
        } else {
            $schema->table('pagos', function (Blueprint $table) {
                $add = function(string $col, callable $def){
                    if (!Schema::connection($this->connection)->hasColumn('pagos', $col)) { $def(); }
                };
                $add('cliente_id', fn()=> $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete());
                $add('monto', fn()=> $table->decimal('monto',12,2));
                $add('fecha', fn()=> $table->timestamp('fecha')->useCurrent()->index());
                $add('estado', fn()=> $table->string('estado',20)->default('pagado')->index());
                $add('metodo_pago', fn()=> $table->string('metodo_pago',50)->nullable());
                $add('referencia', fn()=> $table->string('referencia',100)->nullable());
            });
        }
    }

    public function down(): void {}
};
