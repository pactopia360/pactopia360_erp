<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // plans
        if (!Schema::connection('mysql_admin')->hasTable('plans')) {
            Schema::connection('mysql_admin')->create('plans', function (Blueprint $t) {
                $t->id();
                $t->string('code', 40)->unique(); // 'free','premium'
                $t->string('name', 80);
                $t->decimal('price_month',10,2)->default(0);
                $t->decimal('price_year',10,2)->default(0);
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
            DB::connection('mysql_admin')->table('plans')->insert([
                ['code'=>'free','name'=>'Free','price_month'=>0,'price_year'=>0],
                ['code'=>'premium','name'=>'Premium','price_month'=>999,'price_year'=>999*12],
            ]);
        }

        // promotions
        if (!Schema::connection('mysql_admin')->hasTable('promotions')) {
            Schema::connection('mysql_admin')->create('promotions', function (Blueprint $t) {
                $t->id();
                $t->string('title',100);
                $t->enum('type',['percent','fixed'])->default('percent');
                $t->decimal('value',10,2)->default(0);
                $t->foreignId('plan_id')->nullable()->constrained('plans')->nullOnDelete();
                $t->date('starts_at')->nullable();
                $t->date('ends_at')->nullable();
                $t->string('coupon',50)->nullable()->index();
                $t->integer('max_uses')->nullable();
                $t->integer('used')->default(0);
                $t->boolean('active')->default(true);
                $t->timestamps();
            });
        }

        // developer actions log
        if (!Schema::connection('mysql_admin')->hasTable('dev_actions')) {
            Schema::connection('mysql_admin')->create('dev_actions', function (Blueprint $t) {
                $t->id();
                $t->unsignedBigInteger('user_id')->nullable();
                $t->string('action',80);
                $t->text('payload')->nullable();
                $t->string('ip',45)->nullable();
                $t->timestamps();
            });
        }
    }

    public function down(): void
    {
        // no-op (evita borrar datos en entornos)
    }
};
