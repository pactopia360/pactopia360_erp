<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private string $conn = 'mysql_clientes';
    private string $table = 'sat_user_report_items';

    public function up(): void
    {
        if (Schema::connection($this->conn)->hasTable($this->table)) {
            return;
        }

        Schema::connection($this->conn)->create($this->table, function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('cuenta_id', 50)->index();
            $table->string('usuario_id', 50)->index();
            $table->string('rfc_owner', 20)->index();

            $table->unsignedBigInteger('report_upload_id')->index();
            $table->string('report_type', 30)->nullable();
            $table->string('direction', 20)->nullable()->index();

            $table->unsignedInteger('line_no')->default(0);

            $table->string('uuid', 80)->nullable()->index();
            $table->dateTime('fecha_emision')->nullable()->index();
            $table->string('periodo_ym', 7)->nullable()->index();

            $table->string('emisor_rfc', 20)->nullable()->index();
            $table->string('emisor_nombre', 255)->nullable();

            $table->string('receptor_rfc', 20)->nullable()->index();
            $table->string('receptor_nombre', 255)->nullable();

            $table->string('tipo_comprobante', 10)->nullable()->index();
            $table->string('moneda', 10)->nullable();

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('descuento', 18, 2)->default(0);
            $table->decimal('traslados', 18, 2)->default(0);
            $table->decimal('retenidos', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0)->index();

            $table->json('raw_row')->nullable();
            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['cuenta_id', 'usuario_id', 'rfc_owner', 'direction'], 'sat_uri_owner_dir_idx');
            $table->index(['cuenta_id', 'usuario_id', 'rfc_owner', 'fecha_emision'], 'sat_uri_owner_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::connection($this->conn)->dropIfExists($this->table);
    }
};