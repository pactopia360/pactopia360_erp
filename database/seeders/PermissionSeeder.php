<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class PermissionSeeder extends Seeder
{
    public function run(): void
    {
        $now = Carbon::now();

        // Módulo => acciones (en español para casar con tus middlewares/rutas)
        $map = [
            'usuarios_admin' => ['ver','crear','editar','eliminar','exportar','impersonar'],
            'perfiles'       => ['ver','crear','editar','eliminar','exportar','permisos','toggle'],
            'clientes'       => ['ver','crear','editar','eliminar','exportar'],
            'planes'         => ['ver','crear','editar','eliminar'],
            'pagos'          => ['ver','exportar'],
            'facturacion'    => ['ver','exportar'],
            'auditoria'      => ['ver'],
            'reportes'       => ['ver'],
        ];

        // Mapeo para labels legibles
        $labels = [
            'ver'=>'Ver', 'crear'=>'Crear', 'editar'=>'Editar', 'eliminar'=>'Eliminar',
            'exportar'=>'Exportar', 'impersonar'=>'Impersonar',
            'permisos'=>'Gestionar permisos', 'toggle'=>'Activar/Desactivar',
        ];

        $rows = [];
        foreach ($map as $grupo => $acciones) {
            foreach ($acciones as $a) {
                $rows[] = [
                    'clave'       => "$grupo.$a",
                    'grupo'       => $grupo,
                    'label'       => ($labels[$a] ?? ucfirst($a)).' '.ucfirst(str_replace('_',' ', $grupo)),
                    'activo'      => true,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        // upsert por 'clave' para que sea idempotente
        DB::table('permisos')->upsert($rows, ['clave'], ['grupo','label','activo','updated_at']);
    }
}
