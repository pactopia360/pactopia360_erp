<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
  public function up(): void {
    Schema::connection('mysql_admin')->create('email_verifications', function (Blueprint $t) {
      $t->id();
      $t->unsignedBigInteger('account_id')->index();
      $t->string('email', 150)->index();
      $t->string('token', 64)->unique();
      $t->timestamp('expires_at')->nullable();
      $t->timestamps();
    });
  }
  public function down(): void { Schema::connection('mysql_admin')->dropIfExists('email_verifications'); }
};
