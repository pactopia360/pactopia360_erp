<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_clientes';

    public function up(): void
    {
        $this->ensureSatRegimenesFiscales();
        $this->ensureSatUsosCfdi();
        $this->ensureSatFormasPago();
        $this->ensureSatMetodosPago();
    }

    public function down(): void
    {
        $schema = Schema::connection($this->conn);

        $schema->dropIfExists('sat_metodos_pago');
        $schema->dropIfExists('sat_formas_pago');
        $schema->dropIfExists('sat_usos_cfdi');
        $schema->dropIfExists('sat_regimenes_fiscales');
    }

    private function ensureSatRegimenesFiscales(): void
    {
        $schema = Schema::connection($this->conn);

        if (!$schema->hasTable('sat_regimenes_fiscales')) {
            $schema->create('sat_regimenes_fiscales', function (Blueprint $table) {
                $table->string('clave', 5)->primary();
                $table->string('descripcion', 255);
                $table->boolean('aplica_fisica')->default(false);
                $table->boolean('aplica_moral')->default(false);
                $table->date('vigencia_desde')->nullable();
                $table->date('vigencia_hasta')->nullable();
                $table->timestamps();
            });

            return;
        }

        if (!$schema->hasColumn('sat_regimenes_fiscales', 'descripcion')) {
            $schema->table('sat_regimenes_fiscales', function (Blueprint $table) {
                $table->string('descripcion', 255)->nullable()->after('clave');
            });
        }

        if (!$schema->hasColumn('sat_regimenes_fiscales', 'aplica_fisica')) {
            $schema->table('sat_regimenes_fiscales', function (Blueprint $table) {
                $table->boolean('aplica_fisica')->default(false)->after('descripcion');
            });
        }

        if (!$schema->hasColumn('sat_regimenes_fiscales', 'aplica_moral')) {
            $schema->table('sat_regimenes_fiscales', function (Blueprint $table) {
                $table->boolean('aplica_moral')->default(false)->after('aplica_fisica');
            });
        }

        if (!$schema->hasColumn('sat_regimenes_fiscales', 'vigencia_desde')) {
            $schema->table('sat_regimenes_fiscales', function (Blueprint $table) {
                $table->date('vigencia_desde')->nullable()->after('aplica_moral');
            });
        }

        if (!$schema->hasColumn('sat_regimenes_fiscales', 'vigencia_hasta')) {
            $schema->table('sat_regimenes_fiscales', function (Blueprint $table) {
                $table->date('vigencia_hasta')->nullable()->after('vigencia_desde');
            });
        }

        if (!$schema->hasColumn('sat_regimenes_fiscales', 'created_at')) {
            $schema->table('sat_regimenes_fiscales', function (Blueprint $table) {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!$schema->hasColumn('sat_regimenes_fiscales', 'updated_at')) {
            $schema->table('sat_regimenes_fiscales', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    private function ensureSatUsosCfdi(): void
    {
        $schema = Schema::connection($this->conn);

        if (!$schema->hasTable('sat_usos_cfdi')) {
            $schema->create('sat_usos_cfdi', function (Blueprint $table) {
                $table->string('clave', 5)->primary();
                $table->string('descripcion', 255);
                $table->boolean('aplica_fisica')->default(false);
                $table->boolean('aplica_moral')->default(false);
                $table->json('regimenes_permitidos')->nullable();
                $table->timestamps();
            });

            return;
        }

        if (!$schema->hasColumn('sat_usos_cfdi', 'descripcion')) {
            $schema->table('sat_usos_cfdi', function (Blueprint $table) {
                $table->string('descripcion', 255)->nullable()->after('clave');
            });
        }

        if (!$schema->hasColumn('sat_usos_cfdi', 'aplica_fisica')) {
            $schema->table('sat_usos_cfdi', function (Blueprint $table) {
                $table->boolean('aplica_fisica')->default(false)->after('descripcion');
            });
        }

        if (!$schema->hasColumn('sat_usos_cfdi', 'aplica_moral')) {
            $schema->table('sat_usos_cfdi', function (Blueprint $table) {
                $table->boolean('aplica_moral')->default(false)->after('aplica_fisica');
            });
        }

        if (!$schema->hasColumn('sat_usos_cfdi', 'regimenes_permitidos')) {
            $schema->table('sat_usos_cfdi', function (Blueprint $table) {
                $table->json('regimenes_permitidos')->nullable()->after('aplica_moral');
            });
        }

        if (!$schema->hasColumn('sat_usos_cfdi', 'created_at')) {
            $schema->table('sat_usos_cfdi', function (Blueprint $table) {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!$schema->hasColumn('sat_usos_cfdi', 'updated_at')) {
            $schema->table('sat_usos_cfdi', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    private function ensureSatFormasPago(): void
    {
        $schema = Schema::connection($this->conn);

        if (!$schema->hasTable('sat_formas_pago')) {
            $schema->create('sat_formas_pago', function (Blueprint $table) {
                $table->string('clave', 5)->primary();
                $table->string('descripcion', 255);
                $table->timestamps();
            });

            return;
        }

        if (!$schema->hasColumn('sat_formas_pago', 'descripcion')) {
            $schema->table('sat_formas_pago', function (Blueprint $table) {
                $table->string('descripcion', 255)->nullable()->after('clave');
            });
        }

        if (!$schema->hasColumn('sat_formas_pago', 'created_at')) {
            $schema->table('sat_formas_pago', function (Blueprint $table) {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!$schema->hasColumn('sat_formas_pago', 'updated_at')) {
            $schema->table('sat_formas_pago', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });
        }
    }

    private function ensureSatMetodosPago(): void
    {
        $schema = Schema::connection($this->conn);

        if (!$schema->hasTable('sat_metodos_pago')) {
            $schema->create('sat_metodos_pago', function (Blueprint $table) {
                $table->string('clave', 5)->primary();
                $table->string('descripcion', 255);
                $table->timestamps();
            });

            return;
        }

        if (!$schema->hasColumn('sat_metodos_pago', 'descripcion')) {
            $schema->table('sat_metodos_pago', function (Blueprint $table) {
                $table->string('descripcion', 255)->nullable()->after('clave');
            });
        }

        if (!$schema->hasColumn('sat_metodos_pago', 'created_at')) {
            $schema->table('sat_metodos_pago', function (Blueprint $table) {
                $table->timestamp('created_at')->nullable();
            });
        }

        if (!$schema->hasColumn('sat_metodos_pago', 'updated_at')) {
            $schema->table('sat_metodos_pago', function (Blueprint $table) {
                $table->timestamp('updated_at')->nullable();
            });
        }
    }
};