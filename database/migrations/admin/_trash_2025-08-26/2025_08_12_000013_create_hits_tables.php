<?php
// database/migrations/admin/2025_08_12_000013_create_hits_tables.php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
  public function up(): void {
    if (!Schema::connection('mysql_admin')->hasTable('hits_packs')) {
      Schema::connection('mysql_admin')->create('hits_packs', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->string('name'); // p.ej. "100 Timbres"
        $t->integer('hits_qty');
        $t->decimal('price',10,2);
        $t->boolean('active')->default(true);
        $t->timestamps();
      });
    }

    if (!Schema::connection('mysql_admin')->hasTable('hits_ledger')) {
      Schema::connection('mysql_admin')->create('hits_ledger', function (Blueprint $t) {
        $t->bigIncrements('id');
        $t->unsignedBigInteger('account_id')->index();
        $t->enum('type',['purchase','consume','adjust'])->index();
        $t->integer('hits_delta'); // +N compra / -N consumo
        $t->string('reason')->nullable(); // "CFDI-UUID", "Compra pack 100", etc.
        $t->unsignedBigInteger('payment_id')->nullable();
        $t->timestamps();

        $t->foreign('account_id')->references('id')->on('accounts')->cascadeOnDelete();
        $t->foreign('payment_id')->references('id')->on('payments')->nullOnDelete();
      });
    }
  }
  public function down(): void {
    Schema::connection('mysql_admin')->dropIfExists('hits_ledger');
    Schema::connection('mysql_admin')->dropIfExists('hits_packs');
  }
};
