<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('personal_access_tokens', function (Blueprint $table) {
            $table->id();

            /*
            |--------------------------------------------------------------------------
            | IMPORTANTE
            |--------------------------------------------------------------------------
            | UsuarioCuenta usa UUID string como PK.
            | Por eso Sanctum no debe usar morphs() (bigint), sino uuidMorphs().
            */
            $table->uuidMorphs('tokenable');

            $table->string('name');
            $table->string('token', 64)->unique();
            $table->text('abilities')->nullable();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamps();

            $table->index(['tokenable_type', 'tokenable_id'], 'personal_access_tokens_tokenable_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('personal_access_tokens');
    }
};