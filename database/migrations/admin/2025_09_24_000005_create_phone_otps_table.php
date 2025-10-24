<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    // Pública y SIN tipo
    public $connection = 'mysql_admin';

    public function up(): void
    {
        $conn = Schema::connection($this->connection);

        // Si la tabla YA existe, sólo aseguramos columnas/índices y NO agregamos FK
        if ($conn->hasTable('phone_otps')) {
            $conn->table('phone_otps', function (Blueprint $table) use ($conn) {
                if (!$conn->hasColumn('phone_otps', 'account_id')) {
                    $table->unsignedBigInteger('account_id')->after('id');
                    $table->index('account_id', 'phoneotps_account_id_idx');
                }
                if (!$conn->hasColumn('phone_otps', 'phone')) {
                    $table->string('phone', 32)->nullable()->after('account_id');
                }
                if (!$conn->hasColumn('phone_otps', 'code')) {
                    $table->string('code', 6)->after('phone');
                    $table->index('code', 'phoneotps_code_idx');
                }
                if (!$conn->hasColumn('phone_otps', 'channel')) {
                    $table->string('channel', 10)->default('sms')->after('code'); // sms|whatsapp
                    $table->index('channel', 'phoneotps_channel_idx');
                }
                if (!$conn->hasColumn('phone_otps', 'attempts')) {
                    $table->unsignedSmallInteger('attempts')->default(0)->after('channel');
                }
                if (!$conn->hasColumn('phone_otps', 'expires_at')) {
                    $table->timestamp('expires_at')->nullable()->after('attempts');
                    $table->index('expires_at', 'phoneotps_expires_idx');
                }
                if (!$conn->hasColumn('phone_otps', 'used_at')) {
                    $table->timestamp('used_at')->nullable()->after('expires_at');
                    $table->index('used_at', 'phoneotps_used_idx');
                }
                if (!$conn->hasColumn('phone_otps', 'created_at')) {
                    $table->timestamp('created_at')->nullable()->after('used_at');
                }
                if (!$conn->hasColumn('phone_otps', 'updated_at')) {
                    $table->timestamp('updated_at')->nullable()->after('created_at');
                }
            });
            return;
        }

        // Si NO existía, crearla SIN FK
        $conn->create('phone_otps', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->unsignedBigInteger('account_id');
            $table->string('phone', 32)->nullable();
            $table->string('code', 6);
            $table->string('channel', 10)->default('sms'); // sms|whatsapp
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index('account_id', 'phoneotps_account_id_idx');
            $table->index('code', 'phoneotps_code_idx');
            $table->index('channel', 'phoneotps_channel_idx');
            $table->index('expires_at', 'phoneotps_expires_idx');
            $table->index('used_at', 'phoneotps_used_idx');
        });
    }

    public function down(): void
    {
        // Conservador: no borramos la tabla
        // Schema::connection($this->connection)->dropIfExists('phone_otps');
    }
};
