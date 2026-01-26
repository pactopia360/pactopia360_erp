<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasTable('planes')) {
            return;
        }

        Schema::table('planes', function (Blueprint $table) {
            if (!Schema::hasColumn('planes', 'precio_anual')) {
                // Nullable para no romper planes existentes
                $table->decimal('precio_anual', 12, 2)->nullable()->after('precio_mensual');
            }
        });
    }

    public function down(): void
    {
        if (!Schema::hasTable('planes')) {
            return;
        }

        Schema::table('planes', function (Blueprint $table) {
            if (Schema::hasColumn('planes', 'precio_anual')) {
                $table->dropColumn('precio_anual');
            }
        });
    }
};
