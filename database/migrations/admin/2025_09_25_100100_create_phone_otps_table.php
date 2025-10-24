<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $connection = 'mysql_admin';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('phone_otps')) {
            Schema::connection($this->connection)->create('phone_otps', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('account_id');
                $table->string('phone', 32);
                $table->char('code', 6);
                $table->enum('channel', ['sms', 'whatsapp'])->default('sms');
                $table->unsignedSmallInteger('attempts')->default(0);
                $table->timestamp('expires_at');
                $table->timestamp('used_at')->nullable();
                $table->timestamps();

                $table->index(['account_id']);
                $table->index(['account_id', 'code']);
                $table->index(['expires_at']);
                $table->index(['used_at']);

                $table->foreign('account_id')->references('id')->on('accounts')
                      ->onUpdate('cascade')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('phone_otps');
    }
};
