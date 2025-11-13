<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // 1) sat_downloads: agrega expires_at si no existe
        if (Schema::hasTable('sat_downloads') && !Schema::hasColumn('sat_downloads', 'expires_at')) {
            Schema::table('sat_downloads', function (Blueprint $table) {
                $table->timestamp('expires_at')->nullable()->after('zip_path')->index();
            });
        }

        // 2) sat_templates: créala solo si NO existe
        if (!Schema::hasTable('sat_templates')) {
            Schema::create('sat_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('nombre');
                $table->string('color_hex')->nullable();
                $table->string('archivo_path'); // ruta a plantilla base (si se usa)
                $table->timestamps();
            });
        }

        // 3) sat_empresa_templates (FK condicional a "empresas")
        if (!Schema::hasTable('sat_empresa_templates')) {
            Schema::create('sat_empresa_templates', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->unsignedBigInteger('empresa_id')->nullable()->index();
                $table->unsignedBigInteger('template_id')->nullable()->index();
                $table->string('logo_path')->nullable();     // logo de la empresa
                $table->string('color_hex')->nullable();     // override de color
                $table->timestamps();

                // FK a sat_templates si existe
                if (Schema::hasTable('sat_templates')) {
                    $table->foreign('template_id')->references('id')->on('sat_templates')->cascadeOnDelete();
                }

                // FK a empresas SOLO si existe la tabla
                if (Schema::hasTable('empresas')) {
                    $table->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
                }
            });
        }
    }

    public function down(): void
    {
        // No borramos tablas productivas. Si necesitas rollback, hazlo manual.
    }
};
