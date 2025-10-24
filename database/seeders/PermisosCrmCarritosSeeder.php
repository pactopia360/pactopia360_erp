<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PermisosCrmCarritosSeeder extends Seeder
{
    public function run(): void
    {
        $table = 'permissions'; // ajusta si tu tabla de permisos se llama distinto

        if (!Schema::hasTable($table)) {
            $this->command?->warn("Tabla {$table} no existe. Omitiendo PermisosCrmCarritosSeeder.");
            return;
        }

        $perms = [
            'crm.ver p360',
            'crm.robots p360',
            'crm.carritos.ver',
            'crm.carritos.crear',
            'crm.carritos.editar',
            'crm.carritos.eliminar',
            'crm.carritos.exportar',
        ];

        $has = fn(string $col) => Schema::hasColumn($table, $col);

        foreach ($perms as $p) {
            $row = [];

            if ($has('slug'))       $row['slug'] = $p;
            elseif ($has('name'))   $row['name'] = $p;
            elseif ($has('clave'))  $row['clave'] = $p;

            if ($has('guard_name')) $row['guard_name'] = 'admin';
            if ($has('description')) $row['description'] = 'Permiso auto-sembrado para CRM · Carritos';

            $keyCol = $has('slug') ? 'slug' : ($has('name') ? 'name' : ($has('clave') ? 'clave' : null));
            if (!$keyCol) {
                $this->command?->warn("No hay columna clave (slug/name/clave) en {$table}. Omito '{$p}'.");
                continue;
            }

            $exists = DB::table($table)->where($keyCol, $p)->exists();
            if ($exists) {
                DB::table($table)->where($keyCol, $p)->update(array_filter($row, fn($v) => $v !== null));
            } else {
                DB::table($table)->insert($row);
            }
        }

        $this->command?->info('Permisos CRM · Carritos sembrados/actualizados.');
    }
}
