<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        $schema = Schema::connection($this->connection);

        if (!$schema->hasTable('cfdis')) {
            $schema->create('cfdis', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
                $table->string('uuid', 36)->unique()->nullable(); // opcional si replica del PAC
                $table->string('serie', 25)->nullable();
                $table->string('folio', 25)->nullable();
                $table->timestamp('fecha')->useCurrent()->index();
                $table->decimal('total', 12, 2)->default(0);
                $table->string('status', 20)->default('vigente'); // vigente|cancelado
                $table->timestamps();
            });
        } else {
            $schema->table('cfdis', function (Blueprint $table) {
                $add = function(string $col, callable $def){
                    if (!Schema::connection($this->connection)->hasColumn('cfdis', $col)) { $def(); }
                };
                $add('cliente_id', fn()=> $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete());
                $add('fecha', fn()=> $table->timestamp('fecha')->useCurrent()->index());
                $add('total', fn()=> $table->decimal('total',12,2)->default(0));
                $add('status', fn()=> $table->string('status',20)->default('vigente'));
            });
        }
    }

    public function down(): void {}
};
