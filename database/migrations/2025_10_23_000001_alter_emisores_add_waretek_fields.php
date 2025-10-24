<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $conn  = 'mysql_clientes';
    protected string $table = 'emisores';

    public function up(): void
    {
        $schema = Schema::connection($this->conn);

        // 1) Crear si no existe
        if (!$schema->hasTable($this->table)) {
            $schema->create($this->table, function (Blueprint $t) {
                $t->bigIncrements('id');

                // multicuenta/tenant (opcional)
                $t->unsignedBigInteger('cuenta_id')->nullable()->index();

                // básicos
                $t->string('rfc', 13)->index();
                $t->string('razon_social', 190);
                $t->string('nombre_comercial', 190)->nullable();

                // waretek / admin
                $t->string('email', 190)->nullable();
                $t->string('regimen_fiscal', 10)->nullable(); // mapea a "regimen"
                $t->string('grupo', 50)->nullable();
                $t->json('direccion')->nullable();   // {cp, direccion, ciudad, estado}
                $t->json('certificados')->nullable(); // {csd_key, csd_cer, csd_password, fiel_key, fiel_cer, fiel_password}
                $t->json('series')->nullable();      // [{tipo, serie, folio}]

                // metadata opcional
                $t->string('status', 20)->default('active');
                $t->string('csd_serie', 100)->nullable();
                $t->dateTime('csd_vigencia_hasta')->nullable();

                // id externo para el PAC
                $t->uuid('ext_id')->nullable()->unique();

                $t->timestamps();
                $t->softDeletes();

                // Un RFC por cuenta
                $t->unique(['cuenta_id', 'rfc']);
            });

            return; // listo
        }

        // 2) Si ya existe, agregar lo que falte sin romper
        $schema->table($this->table, function (Blueprint $t) use ($schema) {
            $col = fn(string $c) => $schema->hasColumn($this->table, $c);

            if (!$col('email'))              $t->string('email',190)->nullable()->after('nombre_comercial');
            if (!$col('regimen_fiscal'))     $t->string('regimen_fiscal',10)->nullable()->after('email');
            if (!$col('grupo'))              $t->string('grupo',50)->nullable()->after('regimen_fiscal');
            if (!$col('direccion'))          $t->json('direccion')->nullable()->after('grupo');
            if (!$col('certificados'))       $t->json('certificados')->nullable()->after('direccion');
            if (!$col('series'))             $t->json('series')->nullable()->after('certificados');
            if (!$col('status'))             $t->string('status',20)->default('active')->after('series');
            if (!$col('csd_serie'))          $t->string('csd_serie',100)->nullable()->after('status');
            if (!$col('csd_vigencia_hasta')) $t->dateTime('csd_vigencia_hasta')->nullable()->after('csd_serie');
            if (!$col('ext_id'))             $t->uuid('ext_id')->nullable()->unique()->after('csd_vigencia_hasta');
            if (!$col('deleted_at'))         $t->softDeletes();
        });
    }

    public function down(): void
    {
        // Si prefieres dropear toda la tabla, descomenta:
        // Schema::connection($this->conn)->dropIfExists($this->table);

        // Revertir sólo campos añadidos:
        $schema = Schema::connection($this->conn);
        if (!$schema->hasTable($this->table)) return;

        $schema->table($this->table, function (Blueprint $t) use ($schema) {
            foreach (['email','regimen_fiscal','grupo','direccion','certificados','series','status','csd_serie','csd_vigencia_hasta','ext_id'] as $c) {
                if ($schema->hasColumn($this->table, $c)) $t->dropColumn($c);
            }
        });
    }
};
