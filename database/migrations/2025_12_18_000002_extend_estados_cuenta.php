<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        $schema = Schema::connection('mysql_admin');

        // Si la tabla no existe en esta conexión, no intentes alterarla (evita el FAIL).
        // Importante: esta migración asume que "estados_cuenta" se crea en otra migración previa.
        if (!$schema->hasTable('estados_cuenta')) {
            return;
        }

        $schema->table('estados_cuenta', function (Blueprint $t) use ($schema) {
            if (!$schema->hasColumn('estados_cuenta', 'source')) {
                $t->string('source', 30)
                  ->nullable()
                  ->index()
                  ->comment('system|manual|stripe');
            }

            if (!$schema->hasColumn('estados_cuenta', 'ref')) {
                $t->string('ref', 191)
                  ->nullable()
                  ->index()
                  ->comment('stripe session/invoice/payment intent or folio');
            }

            if (!$schema->hasColumn('estados_cuenta', 'meta')) {
                $t->json('meta')->nullable();
            }
        });
    }

    public function down(): void
    {
        // Down opcional: no elimino columnas por seguridad en datos existentes.
        // Si quieres reversibilidad estricta, lo implementamos cuando confirmes el esquema final.
    }
};
