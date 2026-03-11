<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');

        if (Schema::connection($adm)->hasTable('billing_settings')) {
            return;
        }

        Schema::connection($adm)->create('billing_settings', function (Blueprint $t) {
            $t->bigIncrements('id');

            $t->string('key', 120)->unique();
            $t->longText('value')->nullable();

            $t->timestamps();
        });
    }

    public function down(): void
    {
        $adm = (string) (config('p360.conn.admin') ?: 'mysql_admin');
        Schema::connection($adm)->dropIfExists('billing_settings');
    }
};