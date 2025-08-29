<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    protected string $connection = 'mysql_admin';

    public function getConnection()
    {
        return config('database.connections.mysql_admin') ? 'mysql_admin' : config('database.default');
    }

    public function up(): void
    {
        Schema::connection($this->getConnection())->create('admin_audit_logs', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('actor_id')->nullable()->index();
            $table->string('target_type', 160)->nullable()->index();
            $table->unsignedBigInteger('target_id')->nullable()->index();
            $table->string('action', 120)->index();
            $table->json('changes')->nullable();
            $table->string('ip', 64)->nullable();
            $table->string('user_agent', 512)->nullable();
            $table->timestamps();

            $table->index(['target_type','target_id']);
        });
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->dropIfExists('admin_audit_logs');
    }
};
