<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public $connection = 'mysql_admin';

    public function up(): void
    {
        $conn = Schema::connection($this->connection);

        // 0) Si no existe, créala en formato neutral (ambos campos otp/code)
        if (!$conn->hasTable('phone_otps')) {
            $conn->create('phone_otps', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('account_id')->index();
                $t->string('phone', 32)->nullable();
                $t->string('otp', 6)->nullable();     // neutral
                $t->string('code', 6)->nullable();    // neutral
                $t->string('channel', 10)->default('sms'); // sms|wa
                $t->unsignedSmallInteger('attempts')->default(0);
                $t->timestamp('expires_at')->nullable();
                $t->timestamp('used_at')->nullable();
                $t->timestamps();

                $t->index(['account_id','code']);
                $t->index('expires_at');
                $t->index('used_at');

                // FK best-effort
                try {
                    $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade');
                } catch (\Throwable $e) {}
            });
            return;
        }

        // 1) Si existe, asegurar columnas y tipos neutros
        $conn->table('phone_otps', function (Blueprint $t) use ($conn) {
            if (!$conn->hasColumn('phone_otps','account_id')) {
                $t->unsignedBigInteger('account_id')->nullable()->after('id')->index();
            }
            if (!$conn->hasColumn('phone_otps','phone')) {
                $t->string('phone',32)->nullable()->after('account_id');
            }
            if (!$conn->hasColumn('phone_otps','otp')) {
                $t->string('otp',6)->nullable()->after('phone');
            }
            if (!$conn->hasColumn('phone_otps','code')) {
                $t->string('code',6)->nullable()->after('otp');
                $t->index(['account_id','code']);
            }
            if (!$conn->hasColumn('phone_otps','channel')) {
                $t->string('channel',10)->default('sms')->after('code'); // sms|wa
            }
            if (!$conn->hasColumn('phone_otps','attempts')) {
                $t->unsignedSmallInteger('attempts')->default(0)->after('channel');
            }
            if (!$conn->hasColumn('phone_otps','expires_at')) {
                $t->timestamp('expires_at')->nullable()->after('attempts');
                $t->index('expires_at');
            }
            if (!$conn->hasColumn('phone_otps','used_at')) {
                $t->timestamp('used_at')->nullable()->after('expires_at');
                $t->index('used_at');
            }
            if (!$conn->hasColumn('phone_otps','created_at')) {
                $t->timestamp('created_at')->nullable()->after('used_at');
            }
            if (!$conn->hasColumn('phone_otps','updated_at')) {
                $t->timestamp('updated_at')->nullable()->after('created_at');
            }
        });

        // 2) Intentar FK (best-effort)
        try {
            $conn->table('phone_otps', function (Blueprint $t) {
                try { $t->foreign('account_id')->references('id')->on('accounts')->onDelete('cascade'); }
                catch (\Throwable $e) {}
            });
        } catch (\Throwable $e) {}
    }

    public function down(): void
    {
        // No borramos nada por seguridad; migración es “normalize”.
    }
};
