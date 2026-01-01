<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::connection('mysql_admin')->create('billing_views', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('account_id')->index();
            $t->string('period', 7)->index(); // YYYY-MM
            $t->char('user_id', 36)->nullable()->index(); // usuarios_cuenta.id
            $t->string('event', 30)->index(); // open|view|pdf|pay_click|paid_return|invoice_request
            $t->string('ip', 64)->nullable();
            $t->string('user_agent', 255)->nullable();
            $t->timestamp('created_at')->useCurrent();

            $t->index(['account_id','period','event'], 'idx_billing_views_acc_period_event');
        });

        Schema::connection('mysql_admin')->create('invoice_requests', function (Blueprint $t) {
            $t->bigIncrements('id');
            $t->unsignedBigInteger('account_id')->index();
            $t->string('period', 7)->index();
            $t->enum('status', ['requested','in_progress','done','rejected'])->default('requested')->index();
            $t->char('requested_by_user_id', 36)->nullable()->index();
            $t->text('notes')->nullable();
            $t->timestamps();

            $t->unique(['account_id','period'], 'uq_invoice_req_acc_period');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_admin')->dropIfExists('invoice_requests');
        Schema::connection('mysql_admin')->dropIfExists('billing_views');
    }
};
