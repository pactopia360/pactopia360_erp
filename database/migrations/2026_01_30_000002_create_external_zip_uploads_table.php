<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        Schema::create('external_fiel_uploads', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('account_id');
            $table->string('email_externo')->nullable();
            $table->string('reference', 120)->nullable();
            $table->string('token', 64)->unique();
            $table->string('file_path')->nullable();
            $table->string('status', 20)->default('invited');
            $table->timestamps();
            $table->index(['account_id', 'status'], 'idx_fiel_account_status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('external_fiel_uploads');
    }
};
