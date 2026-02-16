<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_email_events', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->unsignedBigInteger('message_id')->index();

            // open|click|sent|delivered|bounce|complaint|failed|unsub
            $table->string('type', 24)->index();

            // datos del evento
            $table->string('ip', 64)->nullable();
            $table->text('ua')->nullable();

            // click info
            $table->text('url')->nullable();
            $table->string('ref', 190)->nullable();

            $table->json('meta')->nullable();

            $table->timestamp('created_at')->useCurrent();

            $table->foreign('message_id')
                ->references('id')->on('billing_email_messages')
                ->cascadeOnDelete();

            $table->index(['message_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_email_events');
    }
};