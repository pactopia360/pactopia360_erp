<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Ajusta el nombre si tu tabla se llama diferente
        if (!Schema::hasTable('phone_otps')) {
            return;
        }

        Schema::table('phone_otps', function (Blueprint $table) {
            if (!Schema::hasColumn('phone_otps', 'otp')) {
                // Mantén compatible con el insert actual (otp duplicado de code)
                $table->string('otp', 16)->nullable()->after('code');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('phone_otps')) {
            return;
        }

        Schema::table('phone_otps', function (Blueprint $table) {
            if (Schema::hasColumn('phone_otps', 'otp')) {
                $table->dropColumn('otp');
            }
        });
    }
};
