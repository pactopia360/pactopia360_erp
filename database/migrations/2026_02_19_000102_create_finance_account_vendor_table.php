<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('finance_account_vendor', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('account_id'); // id cuenta (admin)
            $t->unsignedBigInteger('vendor_id');
            $t->date('starts_on')->nullable();
            $t->date('ends_on')->nullable();
            $t->boolean('is_primary')->default(true);
            $t->timestamps();

            $t->index(['account_id', 'is_primary']);
            $t->index(['vendor_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('finance_account_vendor');
    }
};
