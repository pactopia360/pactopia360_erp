<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (!Schema::hasTable('audit_logs')) {
            Schema::create('audit_logs', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('action',100);
                $t->string('entity_type',120)->nullable();
                $t->unsignedBigInteger('entity_id')->nullable();
                $t->json('meta')->nullable();
                $t->string('ip',45)->nullable();
                $t->timestamps();
                $t->index(['entity_type','entity_id']);
                $t->index('user_id');
            });
        }
    }
    public function down(): void {}
};
