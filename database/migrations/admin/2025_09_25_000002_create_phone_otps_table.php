<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        Schema::create('phone_otps', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id')->index();
            $table->string('phone', 30)->nullable();
            $table->string('otp', 6);
            $table->enum('channel', ['sms','wa'])->default('sms');
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->foreign('account_id')
                ->references('id')->on('accounts')
                ->onDelete('cascade');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phone_otps');
    }
};
