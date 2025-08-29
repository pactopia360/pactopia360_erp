<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (!Schema::hasTable('jobs')) {
            Schema::create('jobs', function (Blueprint $table) {
                $table->bigIncrements('id');
                $table->string('queue')->index();
                $table->longText('payload');
                $table->unsignedTinyInteger('attempts');
                $table->unsignedInteger('reserved_at')->nullable();
                $table->unsignedInteger('available_at');
                $table->unsignedInteger('created_at');
            });
        } else {
            Schema::table('jobs', function (Blueprint $table) {
                if (!Schema::hasColumn('jobs', 'id')) {
                    $table->bigIncrements('id');
                }
                if (!Schema::hasColumn('jobs', 'queue')) {
                    $table->string('queue')->index()->after('id');
                }
                if (!Schema::hasColumn('jobs', 'payload')) {
                    $table->longText('payload')->after('queue');
                }
                if (!Schema::hasColumn('jobs', 'attempts')) {
                    $table->unsignedTinyInteger('attempts')->after('payload');
                }
                if (!Schema::hasColumn('jobs', 'reserved_at')) {
                    $table->unsignedInteger('reserved_at')->nullable()->after('attempts');
                }
                if (!Schema::hasColumn('jobs', 'available_at')) {
                    $table->unsignedInteger('available_at')->after('reserved_at');
                }
                if (!Schema::hasColumn('jobs', 'created_at')) {
                    $table->unsignedInteger('created_at')->after('available_at');
                }
                // Nota: no intentamos modificar tipos/índices existentes
                // para no requerir doctrine/dbal ni arriesgar datos.
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
    }
};
