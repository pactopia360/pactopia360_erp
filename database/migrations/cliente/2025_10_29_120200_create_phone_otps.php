<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::connection('mysql_admin')->create('phone_otps', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('account_id')->index();
      $t->string('phone', 32)->index();
      $t->string('code', 10)->index(); // duplicamos en 'otp' por compat
      $t->string('otp', 10)->nullable()->index();
      $t->enum('channel', ['sms','whatsapp'])->default('whatsapp');
      $t->unsignedTinyInteger('attempts')->default(0);
      $t->timestamp('expires_at')->nullable();
      $t->timestamp('used_at')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::connection('mysql_admin')->dropIfExists('phone_otps'); }
};
