<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // OJO: tu tabla vive en mysql_clientes típicamente
        Schema::connection('mysql_clientes')->table('external_fiel_uploads', function (Blueprint $table) {
            if (!Schema::connection('mysql_clientes')->hasColumn('external_fiel_uploads', 'fiel_password')) {
                $table->text('fiel_password')->nullable()->after('razon_social');
            }
            if (!Schema::connection('mysql_clientes')->hasColumn('external_fiel_uploads', 'fiel_password_set_at')) {
                $table->timestamp('fiel_password_set_at')->nullable()->after('fiel_password');
            }
        });

        // Fallback por si en algún entorno la tabla está en mysql
        if (Schema::connection('mysql')->hasTable('external_fiel_uploads')) {
            Schema::connection('mysql')->table('external_fiel_uploads', function (Blueprint $table) {
                if (!Schema::connection('mysql')->hasColumn('external_fiel_uploads', 'fiel_password')) {
                    $table->text('fiel_password')->nullable()->after('razon_social');
                }
                if (!Schema::connection('mysql')->hasColumn('external_fiel_uploads', 'fiel_password_set_at')) {
                    $table->timestamp('fiel_password_set_at')->nullable()->after('fiel_password');
                }
            });
        }
    }

    public function down(): void
    {
        Schema::connection('mysql_clientes')->table('external_fiel_uploads', function (Blueprint $table) {
            if (Schema::connection('mysql_clientes')->hasColumn('external_fiel_uploads', 'fiel_password')) {
                $table->dropColumn('fiel_password');
            }
            if (Schema::connection('mysql_clientes')->hasColumn('external_fiel_uploads', 'fiel_password_set_at')) {
                $table->dropColumn('fiel_password_set_at');
            }
        });

        if (Schema::connection('mysql')->hasTable('external_fiel_uploads')) {
            Schema::connection('mysql')->table('external_fiel_uploads', function (Blueprint $table) {
                if (Schema::connection('mysql')->hasColumn('external_fiel_uploads', 'fiel_password')) {
                    $table->dropColumn('fiel_password');
                }
                if (Schema::connection('mysql')->hasColumn('external_fiel_uploads', 'fiel_password_set_at')) {
                    $table->dropColumn('fiel_password_set_at');
                }
            });
        }
    }
};
