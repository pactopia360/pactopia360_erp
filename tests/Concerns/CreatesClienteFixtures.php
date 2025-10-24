<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Str;

trait CreatesClienteFixtures
{
    /** Llama esto en setUp(): habilita SQLite en memoria para *todas* las conexiones y crea esquema */
    protected function useInMemorySqliteForAllConnections(): void
    {
        // Sobrescribe conexiones a sqlite :memory:
        $sqlite = [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => false,
        ];

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite', $sqlite);
        Config::set('database.connections.mysql', $sqlite);
        Config::set('database.connections.mysql_admin', $sqlite);
        Config::set('database.connections.mysql_clientes', $sqlite);

        // Re-conectar para aplicar la config
        DB::purge('sqlite');
        DB::purge('mysql');
        DB::purge('mysql_admin');
        DB::purge('mysql_clientes');

        DB::reconnect('sqlite');
        DB::reconnect('mysql');
        DB::reconnect('mysql_admin');
        DB::reconnect('mysql_clientes');

        // Crea esquemas requeridos
        $this->createAdminSchema();
        $this->createClientesSchema();
    }

    /* =========================
     * SCHEMA: mysql_admin
     * ========================= */

    protected function createAdminSchema(): void
    {
        // accounts
        Schema::connection('mysql_admin')->create('accounts', function (Blueprint $t) {
            $t->increments('id');
            $t->string('email')->index();
            $t->string('rfc')->nullable()->index();
            $t->string('phone')->nullable();
            $t->string('plan')->nullable();
            $t->string('plan_actual')->nullable();
            $t->boolean('is_blocked')->default(false);
            $t->string('estado_cuenta')->nullable();
            $t->timestamp('email_verified_at')->nullable();
            $t->timestamp('phone_verified_at')->nullable();
            $t->timestamps();
        });

        // email_verifications
        Schema::connection('mysql_admin')->create('email_verifications', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->string('email')->index();
            $t->string('token', 120)->unique();
            $t->timestamp('expires_at')->nullable();
            $t->timestamps();
        });

        // phone_otps
        Schema::connection('mysql_admin')->create('phone_otps', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->string('phone')->nullable();
            $t->string('code', 6);
            $t->string('channel', 20)->nullable(); // sms | whatsapp
            $t->timestamp('expires_at');
            $t->unsignedInteger('attempts')->default(0);
            $t->timestamp('used_at')->nullable();
            $t->timestamps();
        });

        // subscriptions
        Schema::connection('mysql_admin')->create('subscriptions', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->string('status')->nullable(); // active | past_due | unpaid
            $t->timestamps();
        });

        // payments
        Schema::connection('mysql_admin')->create('payments', function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('account_id')->index();
            $t->decimal('amount', 12, 2)->default(0);
            $t->string('currency', 8)->default('mxn');
            $t->timestamp('due_date')->nullable();
            $t->string('status')->default('pending'); // pending | paid
            $t->string('method')->nullable(); // stripe
            $t->string('reference')->nullable();
            $t->timestamps();
        });
    }

    /* =========================
     * SCHEMA: mysql_clientes
     * ========================= */

    protected function createClientesSchema(): void
    {
        // cuentas_cliente (UUID pk)
        Schema::connection('mysql_clientes')->create('cuentas_cliente', function (Blueprint $t) {
            $t->string('id')->primary(); // UUID
            $t->string('codigo_cliente')->nullable();
            $t->string('customer_no')->nullable();
            $t->string('rfc_padre')->nullable()->index();
            $t->string('razon_social')->nullable();

            $t->string('plan_actual')->nullable();     // FREE | PRO
            $t->string('modo_cobro')->nullable();      // mensual | anual | null
            $t->string('estado_cuenta')->nullable();   // activa | pago_pendiente | bloqueada_pago | suspendida

            $t->integer('espacio_asignado_mb')->default(0);
            $t->integer('espacio_usado_mb')->default(0);

            $t->integer('hits_asignados')->default(0);
            $t->integer('hits_usados')->default(0);

            $t->integer('max_mass_invoices_per_day')->default(0);
            $t->integer('mass_invoices_used_today')->default(0);
            $t->timestamp('mass_invoices_reset_at')->nullable();

            $t->integer('max_usuarios')->default(1);
            $t->integer('max_empresas')->default(9999);

            $t->unsignedInteger('admin_account_id')->nullable()->index();

            $t->timestamps();
        });

        // usuarios_cuenta (UUID pk)
        Schema::connection('mysql_clientes')->create('usuarios_cuenta', function (Blueprint $t) {
            $t->string('id')->primary();               // UUID
            $t->string('cuenta_id')->index();          // UUID FK (no FK por simplicidad en :memory:)
            $t->string('tipo')->nullable();            // owner | admin | user (legacy)
            $t->string('rol')->nullable();             // owner | admin | user (preferido)
            $t->string('nombre')->nullable();
            $t->string('email')->unique();
            $t->string('phone')->nullable();
            $t->string('password');
            $t->boolean('activo')->default(false);
            $t->boolean('must_change_password')->default(false);
            $t->timestamp('ultimo_login_at')->nullable();
            $t->string('ip_ultimo_login')->nullable();
            $t->integer('sync_version')->default(0);
            $t->rememberToken();
            $t->timestamps();
        });

        // password_reset_tokens
        Schema::connection('mysql_clientes')->create('password_reset_tokens', function (Blueprint $t) {
            $t->string('email')->primary();
            $t->string('token', 120);
            $t->timestamp('created_at')->nullable();
        });
    }

    /* =========================
     * HELPERS DE SEED
     * ========================= */

    /** Crea cuenta/admin FREE y su espejo en clientes. Retorna [admin_id, cuenta_uuid, usuario_uuid]. */
    protected function seedFreeAccount(string $email = 'free@example.com', string $rfc = 'ABC0102039A1'): array
    {
        // admin.accounts
        $adminId = 101;
        DB::connection('mysql_admin')->table('accounts')->insert([
            'id' => $adminId,
            'email' => $email,
            'rfc' => $rfc,
            'phone' => '+5215555555555',
            'plan' => 'FREE',
            'plan_actual' => 'FREE',
            'is_blocked' => 0,
            'estado_cuenta' => 'activa',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // clientes.cuentas_cliente
        $cuentaId = (string) Str::uuid();
        DB::connection('mysql_clientes')->table('cuentas_cliente')->insert([
            'id' => $cuentaId,
            'rfc_padre' => $rfc,
            'plan_actual' => 'FREE',
            'estado_cuenta' => 'activa',
            'admin_account_id' => $adminId,
            'hits_asignados' => 5,
            'hits_usados' => 0,
            'espacio_asignado_mb' => 1024,
            'espacio_usado_mb' => 0,
            'max_usuarios' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // owner
        $userId = (string) Str::uuid();
        DB::connection('mysql_clientes')->table('usuarios_cuenta')->insert([
            'id' => $userId,
            'cuenta_id' => $cuentaId,
            'tipo' => 'owner',
            'rol' => 'owner',
            'email' => $email,
            'password' => bcrypt('x'),
            'activo' => 0,
            'must_change_password' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$adminId, $cuentaId, $userId];
    }

    /** Crea PRO bloqueada + espejo clientes. Retorna [admin_id, cuenta_uuid, user_uuid]. */
    protected function seedProBlocked(string $email = 'pro@example.com', string $rfc = 'XYZ010203AA1'): array
    {
        $adminId = 202;
        DB::connection('mysql_admin')->table('accounts')->insert([
            'id' => $adminId,
            'email' => $email,
            'rfc' => $rfc,
            'phone' => '+521555000000',
            'plan' => 'PRO',
            'plan_actual' => 'PRO',
            'is_blocked' => 1,
            'estado_cuenta' => 'bloqueada_pago',
            'email_verified_at' => now(),
            'phone_verified_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $cuentaId = (string) Str::uuid();
        DB::connection('mysql_clientes')->table('cuentas_cliente')->insert([
            'id' => $cuentaId,
            'rfc_padre' => $rfc,
            'plan_actual' => 'PRO',
            'estado_cuenta' => 'activa',
            'admin_account_id' => $adminId,
            'max_usuarios' => 0, // ilimitado
            'max_mass_invoices_per_day' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $userId = (string) Str::uuid();
        DB::connection('mysql_clientes')->table('usuarios_cuenta')->insert([
            'id' => $userId,
            'cuenta_id' => $cuentaId,
            'tipo' => 'owner',
            'rol' => 'owner',
            'email' => $email,
            'password' => bcrypt('secret'),
            'activo' => 1,
            'must_change_password' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        // Suscripción en past_due para que el middleware redirija a billing
        DB::connection('mysql_admin')->table('subscriptions')->insert([
            'account_id' => $adminId,
            'status' => 'past_due',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return [$adminId, $cuentaId, $userId];
    }

    /** Inserta un token de verificación de email para account */
    protected function seedEmailToken(int $adminAccountId, string $email, string $token = 'TOK'): void
    {
        DB::connection('mysql_admin')->table('email_verifications')->insert([
            'account_id' => $adminAccountId,
            'email'      => $email,
            'token'      => $token,
            'expires_at' => now()->addDay(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** Obtiene el último OTP generado para un account_id */
    protected function getLastOtp(int $adminAccountId): ?\stdClass
    {
        return DB::connection('mysql_admin')->table('phone_otps')
            ->where('account_id', $adminAccountId)
            ->orderByDesc('id')
            ->first();
    }
}
