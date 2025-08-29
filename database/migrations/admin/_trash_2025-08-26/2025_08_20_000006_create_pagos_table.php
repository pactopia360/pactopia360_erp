<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $conn = 'mysql_admin';
        $table = 'pagos';

        if (!Schema::connection($conn)->hasTable($table)) {
            Schema::connection($conn)->create($table, function (Blueprint $t) {
                $t->id();
                $t->uuid('cuenta_id');                         // relación con cuentas.id (uuid)
                $t->string('concepto');
                $t->decimal('monto',10,2);
                $t->string('metodo')->default('transferencia'); // tarjeta|paypal|manual
                $t->enum('estatus',['pendiente','pagado','fallido'])->default('pendiente');
                $t->date('fecha_pago')->nullable();
                $t->timestamps();
                // FK opcional si la integridad ya existe: $t->foreign('cuenta_id')->references('id')->on('cuentas');
            });
            return;
        }

        Schema::connection($conn)->table($table, function (Blueprint $t) use ($conn, $table) {
            $has = fn($col) => Schema::connection($conn)->hasColumn($table, $col);
            if (!$has('cuenta_id'))   $t->uuid('cuenta_id')->nullable()->after('id');
            if (!$has('concepto'))    $t->string('concepto')->nullable()->after('cuenta_id');
            if (!$has('monto'))       $t->decimal('monto',10,2)->default(0)->after('concepto');
            if (!$has('metodo'))      $t->string('metodo',191)->default('transferencia')->after('monto');
            if (!$has('estatus'))     $t->enum('estatus',['pendiente','pagado','fallido'])->default('pendiente')->after('metodo');
            if (!$has('fecha_pago'))  $t->date('fecha_pago')->nullable()->after('estatus');
            if (!$has('created_at'))  $t->timestamps();
        });
    }

    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('pagos');
    }
};
