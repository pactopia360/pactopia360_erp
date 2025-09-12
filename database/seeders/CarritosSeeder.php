<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use App\Models\Empresas\Pactopia360\CRM\Carrito;

class CarritosSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'crm_carritos';

        if (!Schema::hasTable($table)) {
            $this->command?->warn("Tabla {$table} no existe. Omite CarritosSeeder.");
            return;
        }

        foreach (range(1, 10) as $i) {
            $candidate = [
                // esquema “rico”
                'empresa_slug' => 'pactopia360',
                'cliente_id'   => null,
                'contacto_id'  => null,
                'titulo'       => "Carrito demo {$i}",
                'estado'       => ['nuevo','abierto','convertido','cancelado'][array_rand([0,1,2,3])],
                'total'        => rand(1000, 150000) / 100,
                'moneda'       => 'MXN',
                'notas'        => 'Registro de prueba',
                'metadata'     => ['canal' => 'demo'],

                // esquema “simple”
                'cliente'      => "Cliente {$i}",
                'email'        => "cliente{$i}@mail.com",
                'telefono'     => '555-000-' . str_pad((string)$i, 4, '0', STR_PAD_LEFT),
                'origen'       => 'manual',
                'etiquetas'    => ['demo','p360'],
                'meta'         => ['tracking' => 'abc'],
            ];

            // Filtra por columnas que realmente existen en tu tabla
            $data = [];
            foreach ($candidate as $col => $val) {
                if (Schema::hasColumn($table, $col)) {
                    $data[$col] = $val;
                }
            }

            // Defaults razonables si existen esas columnas
            if (!isset($data['estado']) && Schema::hasColumn($table, 'estado')) {
                $data['estado'] = 'nuevo';
            }
            if (!isset($data['moneda']) && Schema::hasColumn($table, 'moneda')) {
                $data['moneda'] = 'MXN';
            }
            if (!isset($data['total']) && Schema::hasColumn($table, 'total')) {
                $data['total'] = 0;
            }

            Carrito::create($data);
        }

        $this->command?->info('Seed de crm_carritos: 10 registros insertados (columnas filtradas por existencia).');
    }
}
