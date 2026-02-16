<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_email_messages', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Vinculación campaña (opcional)
            $table->unsignedBigInteger('campaign_id')->nullable();

            // Contexto del estado de cuenta
            $table->string('account_id', 64)->index();  // en admin puede ser varchar(36) o numérico string
            $table->string('period', 7)->nullable()->index();

            // Destinatario
            $table->string('to_email', 190)->index();
            $table->string('to_name', 190)->nullable();

            // Identificadores del proveedor / cola
            $table->string('provider', 40)->nullable();         // smtp|ses|sendgrid|mailgun|postmark|stripe|manual
            $table->string('provider_message_id', 190)->nullable()->index();
            $table->string('queue_job_id', 190)->nullable();

            // Estado de entrega
            $table->string('status', 24)->default('queued');    // queued|sent|delivered|bounced|failed
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('last_event_at')->nullable();

            // Tracking seguro por token (NO guardamos token plano)
            $table->char('token_hash', 64)->unique();           // sha256 hex
            $table->unsignedInteger('open_count')->default(0);
            $table->unsignedInteger('click_count')->default(0);

            // Datos renderizados/variables (JSON) para auditoría
            $table->json('payload')->nullable(); // subject, template, bank data snapshot, etc.

            $table->timestamps();

            $table->foreign('campaign_id')
                ->references('id')->on('billing_email_campaigns')
                ->nullOnDelete();

            $table->index(['account_id', 'period', 'status']);
            $table->index(['status', 'sent_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_email_messages');
    }
};