<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        $conn = 'mysql_admin';
        $table = 'estados_cuenta';

        if (!Schema::connection($conn)->hasTable($table)) {
            Schema::connection($conn)->create($table, function (Blueprint $t) {
                $t->id();
                $t->uuid('cuenta_id');
                $t->date('periodo');
                $t->decimal('cargo',12,2)->default(0);
                $t->decimal('abono',12,2)->default(0);
                $t->decimal('saldo',12,2)->default(0);
                $t->string('concepto',160)->nullable();
                $t->string('referencia',80)->nullable();
                $t->timestamps();
            });
            return;
        }

        Schema::connection($conn)->table($table, function (Blueprint $t) use ($conn, $table) {
            $has = fn($col) => Schema::connection($conn)->hasColumn($table, $col);
            if (!$has('cuenta_id'))   $t->uuid('cuenta_id')->nullable()->after('id');
            if (!$has('periodo'))     $t->date('periodo')->nullable()->after('cuenta_id');
            if (!$has('cargo'))       $t->decimal('cargo',12,2)->default(0)->after('periodo');
            if (!$has('abono'))       $t->decimal('abono',12,2)->default(0)->after('cargo');
            if (!$has('saldo'))       $t->decimal('saldo',12,2)->default(0)->after('abono');
            if (!$has('concepto'))    $t->string('concepto',160)->nullable()->after('saldo');
            if (!$has('referencia'))  $t->string('referencia',80)->nullable()->after('concepto');
            if (!$has('created_at'))  $t->timestamps();
        });
    }

    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('estados_cuenta');
    }
};
