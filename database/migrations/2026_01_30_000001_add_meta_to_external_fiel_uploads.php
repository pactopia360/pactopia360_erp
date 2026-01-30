<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        Schema::table('external_fiel_uploads', function (Blueprint $table) {
            if (!Schema::hasColumn('external_fiel_uploads', 'rfc')) {
                $table->string('rfc', 13)->nullable()->after('reference');
            }
            if (!Schema::hasColumn('external_fiel_uploads', 'razon_social')) {
                $table->string('razon_social', 190)->nullable()->after('rfc');
            }
            if (!Schema::hasColumn('external_fiel_uploads', 'fiel_password')) {
                $table->text('fiel_password')->nullable()->after('razon_social');
            }
            if (!Schema::hasColumn('external_fiel_uploads', 'file_name')) {
                $table->string('file_name', 255)->nullable()->after('file_path');
            }
            if (!Schema::hasColumn('external_fiel_uploads', 'file_size')) {
                $table->unsignedBigInteger('file_size')->nullable()->after('file_name');
            }
            if (!Schema::hasColumn('external_fiel_uploads', 'mime')) {
                $table->string('mime', 80)->nullable()->after('file_size');
            }
            if (!Schema::hasColumn('external_fiel_uploads', 'uploaded_at')) {
                $table->timestamp('uploaded_at')->nullable()->after('mime');
            }
        });
    }

    public function down(): void {}
};

