<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_admin')->create('billing_commercial_agreements', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('account_id', 64)->index();
            $table->unsignedInteger('admin_account_id')->nullable()->index();

            $table->unsignedTinyInteger('agreed_due_day')->nullable()->comment('Dia del mes pactado para vencimiento/pago');
            $table->boolean('reminders_enabled')->default(true)->comment('Si se permiten recordatorios automaticos');
            $table->unsignedTinyInteger('grace_days')->default(0)->comment('Dias extra antes de marcar vencido');
            $table->date('effective_from')->nullable()->comment('Fecha desde la que aplica el acuerdo');
            $table->date('effective_until')->nullable()->comment('Fecha fin opcional del acuerdo');

            $table->string('status', 20)->default('active')->index()->comment('active|inactive');
            $table->text('notes')->nullable();

            $table->json('meta')->nullable();

            $table->unsignedBigInteger('created_by_admin_id')->nullable()->index();
            $table->unsignedBigInteger('updated_by_admin_id')->nullable()->index();

            $table->timestamps();

            $table->unique(['account_id', 'status'], 'billing_commercial_agreements_account_status_unique');
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_admin')->dropIfExists('billing_commercial_agreements');
    }
};