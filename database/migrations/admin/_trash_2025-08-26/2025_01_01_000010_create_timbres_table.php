<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected $connection = 'mysql_admin';

    public function up(): void
    {
        Schema::connection($this->connection)->create('timbres', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->date('fecha')->index();
            $table->unsignedInteger('uso')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::connection($this->connection)->dropIfExists('timbres');
    }
};
