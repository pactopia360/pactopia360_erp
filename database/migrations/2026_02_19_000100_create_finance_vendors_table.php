<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_vendors', function (Blueprint $t) {
            $t->id();
            $t->string('name', 120);
            $t->string('email', 190)->nullable();
            $t->string('phone', 40)->nullable();
            $t->decimal('default_commission_pct', 6, 3)->nullable(); // ej 3.500
            $t->boolean('is_active')->default(true);
            $t->timestamps();
            $t->index(['is_active', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_vendors');
    }
};
