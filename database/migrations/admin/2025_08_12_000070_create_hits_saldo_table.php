<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('hits_saldo', function (Blueprint $table) {
            $table->id();
            $table->uuid('cuenta_id');
            $table->integer('saldo_actual')->default(0);
            $table->timestamps();

            $table->foreign('cuenta_id')->references('id')->on('cuentas')->onDelete('cascade');
        });
    }
    public function down(): void {
        Schema::dropIfExists('hits_saldo');
    }
};
