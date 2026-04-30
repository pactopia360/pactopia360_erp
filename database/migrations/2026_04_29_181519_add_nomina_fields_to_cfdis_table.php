<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_clientes';

    public function up(): void
    {
        Schema::connection($this->connection)->table('cfdis', function (Blueprint $table) {
            if (!Schema::connection($this->connection)->hasColumn('cfdis', 'empleado_nomina_id')) {
                $table->unsignedBigInteger('empleado_nomina_id')->nullable()->after('receptor_id')->index();
            }

            if (!Schema::connection($this->connection)->hasColumn('cfdis', 'receptor_nomina_json')) {
                $table->json('receptor_nomina_json')->nullable()->after('empleado_nomina_id');
            }
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->table('cfdis', function (Blueprint $table) {
            if (Schema::connection($this->connection)->hasColumn('cfdis', 'receptor_nomina_json')) {
                $table->dropColumn('receptor_nomina_json');
            }

            if (Schema::connection($this->connection)->hasColumn('cfdis', 'empleado_nomina_id')) {
                $table->dropColumn('empleado_nomina_id');
            }
        });
    }
};