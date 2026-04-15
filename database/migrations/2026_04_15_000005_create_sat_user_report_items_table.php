<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';
    protected string $tableName = 'sat_user_report_items';

    public function up(): void
    {
        if (Schema::connection($this->connection)->hasTable($this->tableName)) {
            return;
        }

        Schema::connection($this->connection)->create($this->tableName, function (Blueprint $table) {
            $table->bigIncrements('id');

            $table->string('cuenta_id', 50)->nullable()->index();
            $table->string('usuario_id', 50)->nullable()->index();

            $table->string('rfc_owner', 20)->nullable()->index();

            $table->unsignedBigInteger('report_upload_id')->nullable()->index();
            $table->string('report_type', 60)->nullable()->index();

            $table->string('direction', 10)->nullable()->index();

            $table->unsignedInteger('line_no')->nullable();

            $table->string('uuid', 50)->nullable()->index();

            $table->dateTime('fecha_emision')->nullable()->index();
            $table->string('periodo_ym', 7)->nullable()->index();

            $table->string('emisor_rfc', 20)->nullable()->index();
            $table->string('emisor_nombre', 255)->nullable();

            $table->string('receptor_rfc', 20)->nullable()->index();
            $table->string('receptor_nombre', 255)->nullable();

            $table->string('tipo_comprobante', 10)->nullable();
            $table->string('moneda', 10)->nullable();

            $table->decimal('subtotal', 18, 2)->default(0);
            $table->decimal('descuento', 18, 2)->default(0);
            $table->decimal('traslados', 18, 2)->default(0);
            $table->decimal('retenidos', 18, 2)->default(0);
            $table->decimal('total', 18, 2)->default(0);

            $table->longText('raw_row')->nullable();

            $table->json('meta')->nullable();

            $table->timestamps();

            $table->index(['rfc_owner', 'fecha_emision'], 'suri_rfc_fecha_idx');
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists($this->tableName);
    }
};