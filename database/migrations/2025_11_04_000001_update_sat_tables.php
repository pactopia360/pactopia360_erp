<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        /* =========================
         * sat_downloads: columnas
         * ========================= */
        if (Schema::hasTable('sat_downloads')) {
            Schema::table('sat_downloads', function (Blueprint $t) {
                if (!Schema::hasColumn('sat_downloads', 'cfdi_count')) {
                    $t->integer('cfdi_count')->default(0)->after('zip_path');
                }
                if (!Schema::hasColumn('sat_downloads', 'subtotal')) {
                    $t->decimal('subtotal', 14, 2)->default(0)->after('cfdi_count');
                }
                if (!Schema::hasColumn('sat_downloads', 'iva')) {
                    $t->decimal('iva', 14, 2)->default(0)->after('subtotal');
                }
                if (!Schema::hasColumn('sat_downloads', 'total')) {
                    $t->decimal('total', 14, 2)->default(0)->after('iva');
                }
                if (!Schema::hasColumn('sat_downloads', 'vigencia')) {
                    $t->string('vigencia', 20)->default('Vigente')->after('total');
                }
                if (!Schema::hasColumn('sat_downloads', 'expires_at')) {
                    $t->timestamp('expires_at')->nullable()->after('vigencia');
                }
            });
        }

        /* =========================
         * sat_templates
         * ========================= */
        if (!Schema::hasTable('sat_templates')) {
            Schema::create('sat_templates', function (Blueprint $t) {
                $t->id();
                $t->string('nombre');
                $t->string('color_hex')->nullable();
                $t->string('archivo_path'); // ruta del layout base del PDF
                $t->timestamps();
            });
        }

        /* =========================================================
         * sat_empresa_templates: SIN FK obligatoria (evita error)
         *   - Si existe tabla 'empresas', agrega FK opcionalmente.
         * ========================================================= */
        if (!Schema::hasTable('sat_empresa_templates')) {
            Schema::create('sat_empresa_templates', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('empresa_id')->nullable()->index(); // sin FK dura
                $t->unsignedBigInteger('template_id')->nullable()->index();
                $t->string('logo_path')->nullable();
                $t->timestamps();
            });

            // Si existe 'empresas', intenta agregar FK; si falla, continuamos sin interrumpir
            if (Schema::hasTable('empresas')) {
                try {
                    Schema::table('sat_empresa_templates', function (Blueprint $t) {
                        $t->foreign('empresa_id')->references('id')->on('empresas')->cascadeOnDelete();
                    });
                } catch (\Throwable $e) {
                    // No romper migración si la FK no puede aplicarse
                }
            }

            // FK a sat_templates si existe
            if (Schema::hasTable('sat_templates')) {
                try {
                    Schema::table('sat_empresa_templates', function (Blueprint $t) {
                        $t->foreign('template_id')->references('id')->on('sat_templates')->cascadeOnDelete();
                    });
                } catch (\Throwable $e) {
                    // Ignorar si no se puede crear en este momento
                }
            }
        }

        /* =========================
         * sat_alertas (sin FK dura)
         * ========================= */
        if (!Schema::hasTable('sat_alertas')) {
            Schema::create('sat_alertas', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('cuenta_id')->index(); // evita dependencia dura
                $t->enum('tipo_alerta', ['cancelacion', 'lista_negra', 'iva_discrepancia']);
                $t->text('detalle');
                $t->boolean('enviado_email')->default(false);
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        // Tablas auxiliares
        if (Schema::hasTable('sat_alertas')) {
            Schema::drop('sat_alertas');
        }
        if (Schema::hasTable('sat_empresa_templates')) {
            Schema::drop('sat_empresa_templates');
        }
        if (Schema::hasTable('sat_templates')) {
            Schema::drop('sat_templates');
        }

        // Quitar columnas de sat_downloads si existen
        if (Schema::hasTable('sat_downloads')) {
            Schema::table('sat_downloads', function (Blueprint $t) {
                foreach (['cfdi_count','subtotal','iva','total','vigencia','expires_at'] as $col) {
                    if (Schema::hasColumn('sat_downloads', $col)) {
                        $t->dropColumn($col);
                    }
                }
            });
        }
    }
};
