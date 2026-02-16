<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_email_campaigns', function (Blueprint $table) {
            $table->bigIncrements('id');

            // Identidad funcional (ej: "statement_notice_d1", "statement_lastday_d5", "statement_overdue_d15")
            $table->string('code', 64)->unique();

            // Etiqueta para UI
            $table->string('name', 140);

            // Contexto del envío (estado de cuenta)
            $table->string('period', 7)->nullable();       // YYYY-MM (si aplica)
            $table->string('audience', 32)->default('account'); // account|segment|all

            // Estado
            $table->string('status', 24)->default('draft'); // draft|active|paused|archived

            // Plantilla usada por Mailable (view blade)
            $table->string('template', 190)->nullable(); // ej: admin.mail.statement

            // Asunto base (puede variar por reglas)
            $table->string('subject', 190)->nullable();

            // Reglas / metadata (JSON)
            $table->json('meta')->nullable();

            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();

            $table->timestamps();

            $table->index(['status', 'starts_at']);
            $table->index(['period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_email_campaigns');
    }
};