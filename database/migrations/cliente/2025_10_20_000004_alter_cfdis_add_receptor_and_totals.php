<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $conn = 'mysql_clientes';

    public function up(): void
    {
        // Asegura columnas mínimas para el borrador "Nuevo Documento"
        Schema::connection($this->conn)->table('cfdis', function (Blueprint $t) {
            if (!Schema::connection($this->conn)->hasColumn('cfdis','receptor_id')) {
                $t->unsignedBigInteger('receptor_id')->nullable()->after('cliente_id')->index();
            }
            if (!Schema::connection($this->conn)->hasColumn('cfdis','subtotal')) {
                $t->decimal('subtotal', 14, 4)->default(0)->after('total');
            }
            if (!Schema::connection($this->conn)->hasColumn('cfdis','iva')) {
                $t->decimal('iva', 14, 4)->default(0)->after('subtotal');
            }
            if (!Schema::connection($this->conn)->hasColumn('cfdis','estatus')) {
                $t->string('estatus', 20)->nullable()->change();
            }
            if (!Schema::connection($this->conn)->hasColumn('cfdis','moneda')) {
                $t->string('moneda', 10)->nullable()->after('iva');
            }
            if (!Schema::connection($this->conn)->hasColumn('cfdis','forma_pago')) {
                $t->string('forma_pago', 10)->nullable()->after('moneda');
            }
            if (!Schema::connection($this->conn)->hasColumn('cfdis','metodo_pago')) {
                $t->string('metodo_pago', 10)->nullable()->after('forma_pago');
            }
        });
    }

    public function down(): void
    {
        // No eliminamos columnas por seguridad de datos
    }
};
