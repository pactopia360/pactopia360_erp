<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SatCatalogosSeeder extends Seeder
{
    private string $conn = 'mysql_clientes';

    public function run(): void
    {
        $db = DB::connection($this->conn);

        $this->assertTablesReady();

        $now = now();

        $regimenes = [
            ['601', 'General de Ley Personas Morales', false, true,  '2022-01-01', null],
            ['603', 'Personas Morales con Fines no Lucrativos', false, true, '2022-01-01', null],
            ['605', 'Sueldos y Salarios e Ingresos Asimilados a Salarios', true, false, '2022-01-01', null],
            ['606', 'Arrendamiento', true, false, '2022-01-01', null],
            ['607', 'Régimen de Enajenación o Adquisición de Bienes', true, false, '2022-01-01', null],
            ['608', 'Demás ingresos', true, false, '2022-01-01', null],
            ['610', 'Residentes en el Extranjero sin Establecimiento Permanente en México', true, true, '2022-01-01', null],
            ['611', 'Ingresos por Dividendos (socios y accionistas)', true, false, '2022-01-01', null],
            ['612', 'Personas Físicas con Actividades Empresariales y Profesionales', true, false, '2022-01-01', null],
            ['614', 'Ingresos por intereses', true, false, '2022-01-01', null],
            ['615', 'Régimen de los ingresos por obtención de premios', true, false, '2022-01-01', null],
            ['616', 'Sin obligaciones fiscales', true, false, '2022-01-01', null],
            ['620', 'Sociedades Cooperativas de Producción que optan por diferir sus ingresos', false, true, '2022-01-01', null],
            ['621', 'Incorporación Fiscal', true, false, '2022-01-01', null],
            ['622', 'Actividades Agrícolas, Ganaderas, Silvícolas y Pesqueras', false, true, '2022-01-01', null],
            ['623', 'Opcional para Grupos de Sociedades', false, true, '2022-01-01', null],
            ['624', 'Coordinados', false, true, '2022-01-01', null],
            ['625', 'Régimen de las Actividades Empresariales con ingresos a través de Plataformas Tecnológicas', true, false, '2022-01-01', null],
            ['626', 'Régimen Simplificado de Confianza', true, true, '2022-01-01', null],
        ];

        foreach ($regimenes as $r) {
            $db->table('sat_regimenes_fiscales')->updateOrInsert(
                ['clave' => $r[0]],
                [
                    'descripcion'    => $r[1],
                    'aplica_fisica'  => $r[2] ? 1 : 0,
                    'aplica_moral'   => $r[3] ? 1 : 0,
                    'vigencia_desde' => $r[4],
                    'vigencia_hasta' => $r[5],
                    'created_at'     => $now,
                    'updated_at'     => $now,
                ]
            );
        }

        $usosCfdi = [
            ['G01', 'Adquisición de mercancías', true, true,  ['601','603','606','612','620','621','622','623','624','625','626']],
            ['G02', 'Devoluciones, descuentos o bonificaciones', true, true, ['601','603','606','612','616','620','621','622','623','624','625','626']],
            ['G03', 'Gastos en general', true, true, ['601','603','606','612','620','621','622','623','624','625','626']],
            ['I01', 'Construcciones', true, true, ['601','603','606','612','620','621','622','623','624','625','626']],
            ['I02', 'Mobiliario y equipo de oficina para inversiones', true, true, ['601','603','606','612','620','621','622','623','624','625','626']],
            ['I03', 'Equipo de transporte', true, true, ['601','603','606','612','620','621','622','623','624','625','626']],
            ['I04', 'Equipo de cómputo y accesorios', true, true, ['601','603','606','612','620','621','622','623','624','625','626']],
            ['I05', 'Dados, troqueles, moldes, matrices y herramental', true, true, ['601','603','606','612','620','621','622','623','624','625','626']],
            ['I06', 'Comunicaciones telefónicas', true, true, ['601','603','606','612','620','621','622','623','624','625','626']],
            ['I07', 'Comunicaciones satelitales', true, true, ['601','603','606','612','620','621','622','623','624','625','626']],
            ['I08', 'Otra maquinaria y equipo', true, true, ['601','603','606','612','620','621','622','623','624','625','626']],
            ['D01', 'Honorarios médicos, dentales y hospitalarios', true, false, ['605','606','608','611','612','614','607','615','625']],
            ['D02', 'Gastos médicos por incapacidad o discapacidad', true, false, ['605','606','608','611','612','614','607','615','625']],
            ['D03', 'Gastos funerales', true, false, ['605','606','608','611','612','614','607','615','625']],
            ['D04', 'Donativos', true, false, ['605','606','608','611','612','614','607','615','625']],
            ['D05', 'Intereses reales pagados por créditos hipotecarios', true, false, ['605','606','608','611','612','614','607','615','625']],
            ['D06', 'Aportaciones voluntarias al SAR', true, false, ['605','606','608','611','612','614','607','615','625']],
            ['D07', 'Primas de seguros de gastos médicos', true, false, ['605','606','608','611','612','614','607','615','625']],
            ['D08', 'Gastos de transportación escolar obligatoria', true, false, ['605','606','608','611','612','614','607','615','625']],
            ['D09', 'Depósitos en cuentas para el ahorro, primas de pensiones', true, false, ['605','606','608','611','612','614','607','615','625']],
            ['D10', 'Pagos por servicios educativos (colegiaturas)', true, false, ['605','606','608','611','612','614','607','615','625']],
            ['S01', 'Sin efectos fiscales', true, true, ['601','603','605','606','608','610','611','612','614','616','620','621','622','623','624','607','615','625','626']],
            ['CP01', 'Pagos', true, true, ['601','603','605','606','608','610','611','612','614','616','620','621','622','623','624','607','615','625','626']],
            ['CN01', 'Nómina', true, false, ['605']],
        ];

        foreach ($usosCfdi as $u) {
            $db->table('sat_usos_cfdi')->updateOrInsert(
                ['clave' => $u[0]],
                [
                    'descripcion'          => $u[1],
                    'aplica_fisica'        => $u[2] ? 1 : 0,
                    'aplica_moral'         => $u[3] ? 1 : 0,
                    'regimenes_permitidos' => json_encode($u[4], JSON_UNESCAPED_UNICODE),
                    'created_at'           => $now,
                    'updated_at'           => $now,
                ]
            );
        }

        $formasPago = [
            ['01', 'Efectivo'],
            ['02', 'Cheque'],
            ['03', 'Transferencia'],
            ['04', 'Tarjetas de crédito'],
            ['05', 'Monederos electrónicos'],
            ['06', 'Dinero electrónico'],
            ['07', 'Tarjetas digitales'],
            ['08', 'Vales de despensa'],
            ['09', 'Bienes'],
            ['10', 'Servicio'],
            ['11', 'Por cuenta de tercero'],
            ['12', 'Dación en pago'],
            ['13', 'Pago por subrogación'],
            ['14', 'Pago por consignación'],
            ['15', 'Condonación'],
            ['16', 'Cancelación'],
            ['17', 'Compensación'],
            ['98', 'NA'],
            ['99', 'Otros'],
        ];

        foreach ($formasPago as $f) {
            $db->table('sat_formas_pago')->updateOrInsert(
                ['clave' => $f[0]],
                [
                    'descripcion' => $f[1],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]
            );
        }

        $metodosPago = [
            ['PUE', 'Pago en una sola exhibición'],
            ['PPD', 'Pago en parcialidades o diferido'],
        ];

        foreach ($metodosPago as $m) {
            $db->table('sat_metodos_pago')->updateOrInsert(
                ['clave' => $m[0]],
                [
                    'descripcion' => $m[1],
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ]
            );
        }
    }

    private function assertTablesReady(): void
    {
        $schema = Schema::connection($this->conn);

        $required = [
            'sat_regimenes_fiscales' => [
                'clave', 'descripcion', 'aplica_fisica', 'aplica_moral', 'vigencia_desde', 'vigencia_hasta',
            ],
            'sat_usos_cfdi' => [
                'clave', 'descripcion', 'aplica_fisica', 'aplica_moral', 'regimenes_permitidos',
            ],
            'sat_formas_pago' => [
                'clave', 'descripcion',
            ],
            'sat_metodos_pago' => [
                'clave', 'descripcion',
            ],
        ];

        foreach ($required as $table => $cols) {
            if (!$schema->hasTable($table)) {
                throw new \RuntimeException("Falta la tabla {$table}. Ejecuta primero la migración.");
            }

            foreach ($cols as $col) {
                if (!$schema->hasColumn($table, $col)) {
                    throw new \RuntimeException("Falta la columna {$table}.{$col}. Ejecuta la migración reparada antes del seeder.");
                }
            }
        }
    }
}