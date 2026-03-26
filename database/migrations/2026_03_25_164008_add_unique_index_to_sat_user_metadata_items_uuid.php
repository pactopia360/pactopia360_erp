<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mysql_clientes')->table('sat_user_metadata_items', function (Blueprint $table) {
            $table->unique(
                ['cuenta_id', 'usuario_id', 'rfc_owner', 'direction', 'uuid'],
                'sat_meta_unique_owner_direction_uuid'
            );
        });
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('sat_user_metadata_items', function (Blueprint $table) {
            $table->dropUnique('sat_meta_unique_owner_direction_uuid');
        });
    }
};