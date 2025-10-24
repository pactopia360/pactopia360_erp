<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class InstallTestSchemas
{
    protected static bool $done = false;

    public static function up(): void
    {
        if (self::$done) return;
        self::$done = true;

        // ===== ADMIN =====
        self::admin_accounts();
        self::admin_email_verifications();
        self::admin_phone_otps();
        self::admin_subscriptions();
        self::admin_payments();

        // ===== CLIENTES =====
        self::cliente_cuentas();
        self::cliente_usuarios();
        self::cliente_password_resets();
    }

    protected static function admin_accounts(): void
    {
        Schema::connection('mysql_admin')->create('accounts', function (Blueprint $t) {
            $t->id();
            $t->string('email')->index();
            $t->string('rfc')->nullable()->index();
            $t->string('phone')->nullable();
            $t->string('plan')->nullable();
            $t->string('plan_actual')->nullable();
            $t->boolean('is_blocked')->default(false);
            $t->string('estado_cuenta')->nullable(); // activa, pago_pendiente, bloqueada_pago
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('phone_verified_at')->nullable();
            $t->timestamps();
        });
    }

    protected static function admin_email_verifications(): void
    {
        Schema::connection('mysql_admin')->create('email_verifications', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('account_id')->index();
            $t->string('email')->index();
            $t->string('token', 120)->unique();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });
    }

    protected static function admin_phone_otps(): void
    {
        Schema::connection('mysql_admin')->create('phone_otps', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('account_id')->index();
            $t->string('phone');
            $t->string('code', 6);
            $t->string('channel', 20)->nullable(); // sms|whatsapp
            $t->unsignedSmallInteger('attempts')->default(0);
            $t->timestamp('expires_at')->nullable();
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });
    }

    protected static function admin_subscriptions(): void
    {
        Schema::connection('mysql_admin')->create('subscriptions', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('account_id')->index();
            $t->string('status')->nullable(); // active|past_due|unpaid|canceled
            $t->timestamps();
        });
    }

    protected static function admin_payments(): void
    {
        Schema::connection('mysql_admin')->create('payments', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('account_id')->index();
            $t->string('concepto')->nullable();
            $t->decimal('amount', 10, 2)->default(0);
            $t->string('currency', 10)->default('MXN');
            $t->string('status', 20)->default('pending'); // pending|paid
            $t->string('method', 30)->nullable();
            $t->string('reference', 80)->nullable();
            $t->timestamp('due_date')->nullable();
            $t->timestamps();
        });
    }

    protected static function cliente_cuentas(): void
    {
        Schema::connection('mysql_clientes')->create('cuentas_cliente', function (Blueprint $t) {
            $t->id();
            $t->string('rfc_padre')->index();
            $t->string('plan_actual')->nullable();
            $t->string('estado_cuenta')->nullable(); // activa, etc.
            $t->unsignedBigInteger('admin_account_id')->nullable();
            // Límites/uso (opcionales en tus vistas)
            $t->unsignedInteger('max_usuarios')->nullable();
            $t->unsignedInteger('hits_usados')->nullable();
            $t->unsignedInteger('hits_asignados')->nullable();
            $t->unsignedInteger('espacio_usado_mb')->nullable();
            $t->unsignedInteger('espacio_asignado_mb')->nullable();
            $t->unsignedInteger('mass_invoices_used_today')->nullable();
            $t->unsignedInteger('max_mass_invoices_per_day')->nullable();
            $t->timestamp('mass_invoices_reset_at')->nullable();
            $t->timestamps();
        });
    }

    protected static function cliente_usuarios(): void
    {
        Schema::connection('mysql_clientes')->create('usuarios_cuenta', function (Blueprint $t) {
            $t->id();
            $t->unsignedBigInteger('cuenta_id')->index();
            $t->string('tipo', 20)->default('member'); // owner|member
            $t->string('email')->index();
            $t->string('password');
            $t->boolean('activo')->default(true);
            $t->boolean('must_change_password')->default(false);
            $t->string('nombre')->nullable();
            $t->timestamps();
        });
    }

    protected static function cliente_password_resets(): void
    {
        Schema::connection('mysql_clientes')->create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token', 120);
            $t->timestamp('created_at')->nullable();
        });
    }
}
