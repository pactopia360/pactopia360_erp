<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('sat_credentials')) return;

        Schema::create('sat_credentials', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->char('cuenta_id', 36);
            $table->string('rfc', 13)->index();
            $table->string('cer_path', 191)->nullable();
            $table->string('key_path', 191)->nullable();
            $table->text('key_password_enc')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('validated_at')->nullable();
            $table->timestamps();

            $table->index(['cuenta_id','rfc']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sat_credentials');
    }
};
