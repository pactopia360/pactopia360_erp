<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Forzamos esta migración a la conexión admin cuando se ejecute con --database */
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        // --- USUARIOS ADMINISTRATIVOS ---
        if (Schema::hasTable('usuario_administrativos')) {
            Schema::table('usuario_administrativos', function (Blueprint $table) {
                if (!Schema::hasColumn('usuario_administrativos', 'codigo_usuario')) {
                    $table->string('codigo_usuario', 64)->nullable()->unique()->after('email');
                }
                if (!Schema::hasColumn('usuario_administrativos', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable()->after('password');
                }
                if (!Schema::hasColumn('usuario_administrativos', 'force_password_change')) {
                    $table->boolean('force_password_change')->default(false)->after('password');
                }
            });
        } elseif (Schema::hasTable('admin_users')) {
            Schema::table('admin_users', function (Blueprint $table) {
                if (!Schema::hasColumn('admin_users', 'codigo_usuario')) {
                    $table->string('codigo_usuario', 64)->nullable()->unique()->after('email');
                }
                if (!Schema::hasColumn('admin_users', 'email_verified_at')) {
                    $table->timestamp('email_verified_at')->nullable()->after('password');
                }
                if (!Schema::hasColumn('admin_users', 'force_password_change')) {
                    $table->boolean('force_password_change')->default(false)->after('password');
                }
            });
        }

        // --- CLIENTES (ADMIN) ---
        if (Schema::hasTable('clientes')) {
            Schema::table('clientes', function (Blueprint $table) {
                if (!Schema::hasColumn('clientes', 'rfc')) {
                    $table->string('rfc', 20)->nullable()->after('nombre_comercial');
                }
                if (!Schema::hasColumn('clientes', 'codigo_cliente')) {
                    $table->string('codigo_cliente', 64)->nullable()->unique()->after('rfc');
                }
                if (!Schema::hasColumn('clientes', 'plan')) {
                    $table->string('plan', 20)->nullable()->after('codigo_cliente'); // 'free' | 'premium'
                }
                if (!Schema::hasColumn('clientes', 'billing_cycle')) {
                    $table->string('billing_cycle', 20)->nullable()->after('plan'); // 'monthly' | 'annual'
                }
                if (!Schema::hasColumn('clientes', 'status')) {
                    $table->string('status', 20)->default('active')->after('billing_cycle'); // active|blocked|pending
                }
                if (!Schema::hasColumn('clientes', 'blocked_at')) {
                    $table->timestamp('blocked_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('clientes', 'next_billing_at')) {
                    $table->date('next_billing_at')->nullable()->after('blocked_at');
                }
                if (!Schema::hasColumn('clientes', 'hits_quota')) {
                    $table->unsignedInteger('hits_quota')->default(0)->after('next_billing_at');
                }
                if (!Schema::hasColumn('clientes', 'storage_gb')) {
                    $table->unsignedInteger('storage_gb')->default(1)->after('hits_quota');
                }
            });

            // Índice único para RFC si está presente y aún no es único
            if (Schema::hasColumn('clientes', 'rfc')) {
                // Evita error si ya existe un índice llamado 'clientes_rfc_unique'
                try {
                    Schema::table('clientes', function (Blueprint $table) {
                        $table->unique('rfc', 'clientes_rfc_unique');
                    });
                } catch (\Throwable $e) {
                    // silencioso: índice ya existe o datos duplicados en local
                }
            }
        } elseif (Schema::hasTable('customers')) {
            Schema::table('customers', function (Blueprint $table) {
                if (!Schema::hasColumn('customers', 'rfc')) {
                    $table->string('rfc', 20)->nullable()->after('name');
                }
                if (!Schema::hasColumn('customers', 'codigo_cliente')) {
                    $table->string('codigo_cliente', 64)->nullable()->unique()->after('rfc');
                }
                if (!Schema::hasColumn('customers', 'plan_code')) {
                    $table->string('plan_code', 20)->nullable()->after('codigo_cliente');
                }
                if (!Schema::hasColumn('customers', 'billing_cycle')) {
                    $table->string('billing_cycle', 20)->nullable()->after('plan_code');
                }
                if (!Schema::hasColumn('customers', 'status')) {
                    $table->string('status', 20)->default('active')->after('billing_cycle');
                }
                if (!Schema::hasColumn('customers', 'blocked_at')) {
                    $table->timestamp('blocked_at')->nullable()->after('status');
                }
                if (!Schema::hasColumn('customers', 'next_billing_at')) {
                    $table->date('next_billing_at')->nullable()->after('blocked_at');
                }
                if (!Schema::hasColumn('customers', 'hits_quota')) {
                    $table->unsignedInteger('hits_quota')->default(0)->after('next_billing_at');
                }
                if (!Schema::hasColumn('customers', 'storage_gb')) {
                    $table->unsignedInteger('storage_gb')->default(1)->after('hits_quota');
                }
            });
            if (Schema::hasColumn('customers', 'rfc')) {
                try {
                    Schema::table('customers', function (Blueprint $table) {
                        $table->unique('rfc', 'customers_rfc_unique');
                    });
                } catch (\Throwable $e) { /* noop */ }
            }
        }

        // --- PROMOCIONES (opcional si no existiera) ---
        if (!Schema::hasTable('promociones') && !Schema::hasTable('promotions')) {
            Schema::create('promociones', function (Blueprint $table) {
                $table->id();
                $table->string('titulo', 100);
                $table->enum('tipo', ['descuento_fijo','porcentaje']);
                $table->decimal('valor', 10, 2)->default(0);
                $table->string('plan', 20)->nullable(); // free|premium
                $table->date('fecha_inicio')->nullable();
                $table->date('fecha_fin')->nullable();
                $table->string('codigo_cupon', 50)->nullable()->unique();
                $table->unsignedInteger('uso_maximo')->nullable();
                $table->unsignedInteger('usos_actuales')->default(0);
                $table->boolean('activa')->default(true);
                $table->timestamps();
            });
        }
    }

    public function down(): void
    {
        // No se eliminan columnas para evitar pérdida de datos (safe down)
    }
};
