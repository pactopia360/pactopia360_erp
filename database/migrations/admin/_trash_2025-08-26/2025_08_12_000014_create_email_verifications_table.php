<?php
// database/migrations/admin/2025_08_12_000014_create_email_verifications_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_admin')->hasTable('email_verifications')) return;
    Schema::connection('mysql_admin')->create('email_verifications', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->string('email')->index();
      $t->string('token')->unique(); // firmado/aleatorio
      $t->timestamp('expires_at');
      $t->boolean('used')->default(false);
      $t->timestamps();
    });
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('email_verifications');
  }
};
