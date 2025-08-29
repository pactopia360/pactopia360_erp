<?php
// database/migrations/admin/2025_08_12_000015_create_sync_outbox_table.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (Schema::connection('mysql_admin')->hasTable('sync_outbox')) return;
    Schema::connection('mysql_admin')->create('sync_outbox', function (Blueprint $t) {
      $t->bigIncrements('id');
      $t->string('direction')->default('to_client'); // to_client / to_admin
      $t->unsignedBigInteger('account_id')->nullable();
      $t->string('model');   // 'account_users','hits_ledger', etc.
      $t->string('action');  // 'upsert','delete'
      $t->json('payload');   // data listo para aplicar en destino
      $t->enum('status',['pending','sent','error'])->default('pending');
      $t->text('error_message')->nullable();
      $t->timestamp('available_at')->nullable(); // reintentos
      $t->timestamps();
    });
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('sync_outbox');
  }
};
