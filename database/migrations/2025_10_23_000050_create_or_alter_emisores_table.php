<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    protected string $conn = 'mysql_clientes';
    protected string $table = 'emisores';

    public function up(): void
    {
        // 1) Crear si no existe
        if (!Schema::connection($this->conn)->hasTable($this->table)) {
            Schema::connection($this->conn)->create($this->table, function (Blueprint $t) {
                $t->bigIncrements('id');

                // relación con la cuenta/tenant (opcional, pero muy útil)
                $t->unsignedBigInteger('cuenta_id')->nullable()->index();

                // básicos
                $t->string('rfc', 13)->index();
                $t->string('razon_social', 190);
                $t->string('nombre_comercial', 190)->nullable();

                // nuevos (Waretek / administración)
                $t->string('email', 190)->nullable();
                $t->string('regimen_fiscal', 10)->nullable(); // mapea a "regimen" del PAC
                $t->string('grupo', 50)->nullable();
                $t->json('direccion')->nullable();   // {cp, direccion, ciudad, estado}
                $t->json('certificados')->nullable(); // {csd_key, csd_cer, csd_password, fiel_key, fiel_cer, fiel_password}
                $t->json('series')->nullable();      // [{tipo, serie, folio}]

                // metadatos de certificado (si luego los calculas)
                $t->string('status', 20)->default('active'); // active/suspended/etc
                $t->string('csd_serie', 100)->nullable();
                $t->dateTime('csd_vigencia_hasta')->nullable();

                // id externo que enviaremos al PAC
                $t->uuid('ext_id')->nullable()->unique();

                $t->timestamps();
                $t->softDeletes();

                // índice compuesto opcional útil: un RFC por cuenta
                $t->unique(['cuenta_id', 'rfc']);
            });
            return;
        }

        // 2) Si ya existe, agregar faltantes sin romper
        Schema::connection($this->conn)->table($this->table, function (Blueprint $t) {
            $schema = Schema::connection($this->conn);

            if (!$schema->hasColumn($this->table, 'email'))             $t->string('email',190)->nullable()->after('nombre_comercial');
            if (!$schema->hasColumn($this->table, 'regimen_fiscal'))    $t->string('regimen_fiscal',10)->nullable()->after('email');
            if (!$schema->hasColumn($this->table, 'grupo'))             $t->string('grupo',50)->nullable()->after('regimen_fiscal');
            if (!$schema->hasColumn($this->table, 'direccion'))         $t->json('direccion')->nullable()->after('grupo');
            if (!$schema->hasColumn($this->table, 'certificados'))      $t->json('certificados')->nullable()->after('direccion');
            if (!$schema->hasColumn($this->table, 'series'))            $t->json('series')->nullable()->after('certificados');
            if (!$schema->hasColumn($this->table, 'status'))            $t->string('status',20)->default('active')->after('series');
            if (!$schema->hasColumn($this->table, 'csd_serie'))         $t->string('csd_serie',100)->nullable()->after('status');
            if (!$schema->hasColumn($this->table, 'csd_vigencia_hasta'))$t->dateTime('csd_vigencia_hasta')->nullable()->after('csd_serie');
            if (!$schema->hasColumn($this->table, 'ext_id'))            $t->uuid('ext_id')->nullable()->unique()->after('csd_vigencia_hasta');
            if (!$schema->hasColumn($this->table, 'deleted_at'))        $t->softDeletes();
        });
    }

    public function down(): void
    {
        // Si quieres borrar todo:
        // Schema::connection($this->conn)->dropIfExists($this->table);

        // O sólo revertir columnas añadidas:
        Schema::connection($this->conn)->table($this->table, function (Blueprint $t) {
            $schema = Schema::connection($this->conn);
            foreach (['email','regimen_fiscal','grupo','direccion','certificados','series','status','csd_serie','csd_vigencia_hasta','ext_id'] as $col) {
                if ($schema->hasColumn($this->table, $col)) $t->dropColumn($col);
            }
        });
    }
};
