<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create('email_verifications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('usuario_id');
            $table->string('token')->unique();
            $table->timestamp('expira_at');
            $table->timestamp('usado_at')->nullable();
            $table->timestamps();

            $table->foreign('usuario_id')->references('id')->on('usuarios_cuenta')->onDelete('cascade');
        });
    }

    public function down(): void {
        Schema::dropIfExists('email_verifications');
    }
};
