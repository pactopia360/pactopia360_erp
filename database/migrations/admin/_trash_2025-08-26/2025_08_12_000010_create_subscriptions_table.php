<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_admin')->hasTable('subscriptions')) return;

    Schema::connection('mysql_admin')->create('subscriptions', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->unsignedBigInteger('account_id')->index();          // FK accounts.id (admin)
      $t->unsignedBigInteger('plan_id')->nullable()->index(); // FK plans.id
      $t->enum('status', ['trial','active','past_due','canceled','paused'])->default('trial');
      $t->enum('billing_cycle', ['monthly','annual'])->default('monthly');
      $t->date('current_period_start')->nullable();
      $t->date('current_period_end')->nullable();  // fecha de corte (p/ bloqueo si impago)
      $t->date('next_invoice_date')->nullable();
      $t->integer('grace_days')->default(5);       // “día 5” que pediste
      $t->boolean('auto_renew')->default(true);
      $t->timestamps();
    });
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('subscriptions');
  }
};
