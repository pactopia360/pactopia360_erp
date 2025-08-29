<?php
// database/migrations/admin/2025_08_12_000005_create_payments_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        if (Schema::connection('mysql_admin')->hasTable('payments')) return;

        Schema::connection('mysql_admin')->create('payments', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('account_id');
            $t->unsignedBigInteger('subscription_id')->nullable();
            $t->string('gateway', 30)->nullable(); // stripe/conekta/etc.
            $t->string('gateway_txn_id')->nullable();
            $t->decimal('amount',10,2);
            $t->enum('status',['pending','paid','failed','refunded'])->default('pending');
            $t->json('meta')->nullable();
            $t->timestamp('paid_at')->nullable();
            $t->timestamps();
            $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        });
    }
    public function down(): void {
        Schema::connection('mysql_admin')->dropIfExists('payments');
    }
};
