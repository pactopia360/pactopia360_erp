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
        if (Schema::connection($this->connection)->hasTable('subscriptions')) {
            Schema::connection($this->connection)->table('subscriptions', function (Blueprint $table) {
                // account_id
                if (!Schema::connection($this->connection)->hasColumn('subscriptions', 'account_id')) {
                    $table->unsignedBigInteger('account_id')->after('id');
                    $table->index('account_id', 'subs_account_id_idx');
                }
                // plan
                if (!Schema::connection($this->connection)->hasColumn('subscriptions', 'plan')) {
                    $table->string('plan', 32)->default('PRO')->after('account_id');
                }
                // status
                if (!Schema::connection($this->connection)->hasColumn('subscriptions', 'status')) {
                    $table->string('status', 32)->default('active')->after('plan');
                    $table->index('status', 'subs_status_idx');
                }
                // started_at
                if (!Schema::connection($this->connection)->hasColumn('subscriptions', 'started_at')) {
                    $table->timestamp('started_at')->nullable()->after('status');
                }
                // current_period_end
                if (!Schema::connection($this->connection)->hasColumn('subscriptions', 'current_period_end')) {
                    $table->timestamp('current_period_end')->nullable()->after('started_at');
                }
                // meta
                if (!Schema::connection($this->connection)->hasColumn('subscriptions', 'meta')) {
                    $table->json('meta')->nullable()->after('current_period_end');
                }
                // timestamps
                if (!Schema::connection($this->connection)->hasColumn('subscriptions', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->after('meta');
                }
                if (!Schema::connection($this->connection)->hasColumn('subscriptions', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
            });

            // Listo: no recreamos la tabla si ya existe
            return;
        }

        // Si no existía, se crea completa
        Schema::connection($this->connection)->create('subscriptions', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->string('plan', 32);
            $table->string('status', 32)->default('active');
            $table->timestamp('started_at')->nullable();
            $table->timestamp('current_period_end')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();

            $table->index('account_id', 'subs_account_id_idx');
            $table->index('status', 'subs_status_idx');

            // Si tu MariaDB permite FK sin problema, puedes dejarla;
            // si alguna vez te da lata, coméntala.
            $table->foreign('account_id')
                ->references('id')->on('accounts')
                ->onUpdate('cascade')->onDelete('cascade');
        });
    }

    public function down(): void
    {
        // Evita borrar si existía de antes; solo limpia columnas que agregamos
        if (Schema::connection($this->connection)->hasTable('subscriptions')) {
            Schema::connection($this->connection)->table('subscriptions', function (Blueprint $table) {
                foreach (['account_id','plan','status','started_at','current_period_end','meta','created_at','updated_at'] as $col) {
                    if (Schema::connection($this->connection)->hasColumn('subscriptions', $col)) {
                        $table->dropColumn($col);
                    }
                }
            });
        }
    }
};
