<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            ['clave'=>'basico','nombre'=>'Básico','precio_mensual'=>0,'activo'=>1],
            ['clave'=>'pro','nombre'=>'Pro','precio_mensual'=>499,'activo'=>1],
            ['clave'=>'premium','nombre'=>'Premium','precio_mensual'=>999,'activo'=>1],
            ['clave'=>'enterprise','nombre'=>'Enterprise','precio_mensual'=>1999,'activo'=>1],
        ];
        foreach ($rows as $r){
            DB::table('planes')->updateOrInsert(['clave'=>$r['clave']], $r);
        }
    }
}
