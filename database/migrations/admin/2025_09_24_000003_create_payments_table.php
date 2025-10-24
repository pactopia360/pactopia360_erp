<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Importante: pública y SIN tipo
    public $connection = 'mysql_admin';

    public function up(): void
    {
        // Si la tabla YA existe, solo aseguramos columnas/índices mínimos
        if (Schema::connection($this->connection)->hasTable('payments')) {
            Schema::connection($this->connection)->table('payments', function (Blueprint $table) {
                // account_id
                if (!Schema::connection($this->connection)->hasColumn('payments', 'account_id')) {
                    $table->unsignedBigInteger('account_id')->after('id');
                    $table->index('account_id', 'payments_account_id_idx');
                }
                // amount
                if (!Schema::connection($this->connection)->hasColumn('payments', 'amount')) {
                    $table->unsignedBigInteger('amount')->default(0)->after('account_id');
                }
                // currency
                if (!Schema::connection($this->connection)->hasColumn('payments', 'currency')) {
                    $table->string('currency', 10)->default('MXN')->after('amount');
                }
                // status
                if (!Schema::connection($this->connection)->hasColumn('payments', 'status')) {
                    $table->string('status', 20)->default('pending')->after('currency');
                    $table->index('status', 'payments_status_idx');
                }
                // due_date
                if (!Schema::connection($this->connection)->hasColumn('payments', 'due_date')) {
                    $table->timestamp('due_date')->nullable()->after('status');
                }
                // paid_at
                if (!Schema::connection($this->connection)->hasColumn('payments', 'paid_at')) {
                    $table->timestamp('paid_at')->nullable()->after('due_date');
                    $table->index('paid_at', 'payments_paid_at_idx');
                }
                // Stripe refs
                if (!Schema::connection($this->connection)->hasColumn('payments', 'stripe_session_id')) {
                    $table->string('stripe_session_id', 191)->nullable()->after('paid_at');
                    $table->index('stripe_session_id', 'payments_stripe_sess_idx');
                }
                if (!Schema::connection($this->connection)->hasColumn('payments', 'stripe_payment_intent')) {
                    $table->string('stripe_payment_intent', 191)->nullable()->after('stripe_session_id');
                    $table->index('stripe_payment_intent', 'payments_stripe_pi_idx');
                }
                if (!Schema::connection($this->connection)->hasColumn('payments', 'stripe_invoice_id')) {
                    $table->string('stripe_invoice_id', 191)->nullable()->after('stripe_payment_intent');
                    $table->index('stripe_invoice_id', 'payments_stripe_inv_idx');
                }
                // meta
                if (!Schema::connection($this->connection)->hasColumn('payments', 'meta')) {
                    $table->json('meta')->nullable()->after('stripe_invoice_id');
                }
                // timestamps (si faltaran)
                if (!Schema::connection($this->connection)->hasColumn('payments', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->after('meta');
                }
                if (!Schema::connection($this->connection)->hasColumn('payments', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
            });

            // Listo: no recreamos la tabla si ya existe
            return;
        }

        // Si no existía, se crea completa
        Schema::connection($this->connection)->create('payments', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->unsignedBigInteger('amount')->default(0);
            $table->string('currency', 10)->default('MXN');
            $table->string('status', 20)->default('pending');
            $table->timestamp('due_date')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->string('stripe_session_id', 191)->nullable();
            $table->string('stripe_payment_intent', 191)->nullable();
            $table->string('stripe_invoice_id', 191)->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('account_id', 'payments_account_id_idx');
            $table->index('status', 'payments_status_idx');
            $table->index('paid_at', 'payments_paid_at_idx');
            $table->index('stripe_session_id', 'payments_stripe_sess_idx');
            $table->index('stripe_payment_intent', 'payments_stripe_pi_idx');
            $table->index('stripe_invoice_id', 'payments_stripe_inv_idx');

            // Si tu MariaDB/permiso FK molesta, puedes comentar estas líneas:
            $table->foreign('account_id')
                ->references('id')->on('accounts')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // No borramos la tabla si existía de antes; solo limpiamos columnas agregadas
        if (Schema::connection($this->connection)->hasTable('payments')) {
            Schema::connection($this->connection)->table('payments', function (Blueprint $table) {
                foreach ([
                    'account_id','amount','currency','status','due_date','paid_at',
                    'stripe_session_id','stripe_payment_intent','stripe_invoice_id','meta',
                    'created_at','updated_at'
                ] as $col) {
                    if (Schema::connection($this->connection)->hasColumn('payments', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
