<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_admin')->create('sat_discount_codes', function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('code', 64)->unique();
            $table->string('label', 140)->nullable();

            // percent | fixed
            $table->string('type', 16)->default('percent');
            $table->unsignedTinyInteger('pct')->default(0); // 0-90
            $table->decimal('amount_mxn', 10, 2)->default(0);

            // global | account | partner
            $table->string('scope', 16)->default('global');
            $table->string('account_id', 64)->nullable();

            // socio | distribuidor
            $table->string('partner_type', 16)->nullable();
            $table->string('partner_id', 64)->nullable();

            $table->boolean('active')->default(true);

            $table->dateTime('starts_at')->nullable();
            $table->dateTime('ends_at')->nullable();

            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('uses_count')->default(0);

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['active', 'scope']);
            $table->index(['account_id']);
            $table->index(['partner_type', 'partner_id']);
            $table->index(['starts_at', 'ends_at']);
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_admin')->dropIfExists('sat_discount_codes');
    }
};
