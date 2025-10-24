<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $connection = 'mysql_admin';

    public function up(): void
    {
        if (!Schema::connection($this->connection)->hasTable('email_verifications')) {
            Schema::connection($this->connection)->create('email_verifications', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('account_id');
                $table->string('email', 150);
                $table->string('token', 80)->unique();
                $table->timestamp('expires_at')->nullable();
                $table->timestamps();

                $table->index(['account_id', 'email']);
                $table->foreign('account_id')->references('id')->on('accounts')
                      ->onUpdate('cascade')->onDelete('cascade');
            });
        }
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('email_verifications');
    }
};
