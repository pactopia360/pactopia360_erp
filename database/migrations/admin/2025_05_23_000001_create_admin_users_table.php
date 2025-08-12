<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// database/migrations/2025_05_23_000001_create_admin_users_table.php
return new class extends Migration {
    public function up(): void {
        Schema::create('admin_users', function (Blueprint $t) {
            $t->id();
            $t->string('nombre', 120);
            $t->string('email')->unique();
            $t->string('password');
            $t->enum('rol', ['superadmin','ventas','soporte','dev','conta'])->default('soporte');
            $t->boolean('activo')->default(true);
            $t->timestamp('email_verified_at')->nullable();
            $t->ipAddress('last_login_ip')->nullable();
            $t->timestamp('last_login_at')->nullable();
            $t->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists('admin_users'); }
};