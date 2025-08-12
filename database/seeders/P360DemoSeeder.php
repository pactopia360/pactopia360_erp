<?php

namespace Database\Seeders;

// database/seeders/P360DemoSeeder.php
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Carbon\Carbon;

class P360DemoSeeder extends Seeder {
    public function run(): void {
        // 1) Módulos base
        $mods = [
            ['slug'=>'cfdi','tier'=>'FREE','activo'=>1],
            ['slug'=>'rrhh','tier'=>'PRO','activo'=>1],
            ['slug'=>'nomina','tier'=>'PRO','activo'=>1],
        ];
        foreach ($mods as $m) {
            DB::connection('admin')->table('modulos')->updateOrInsert(
                ['slug'=>$m['slug']],
                ['id'=>Str::ulid(), 'tier'=>$m['tier'], 'activo'=>$m['activo'], 'created_at'=>now(), 'updated_at'=>now()]
            );
        }

        // 2) Cuentas demo
        $cuentas = [
            [
                'rfc_padre'     => 'XAXX010101000', // RFC genérico
                'nombre_cuenta' => 'Pactopia Demo 1',
                'email_owner'   => 'owner1@demo.com',
                'plan'          => 'FREE',
                'periodicidad'  => null,
                'hits_incluidos'=> 20, 'espacio_gb'=>1,
            ],
            [
                'rfc_padre'     => 'PAGM800101ABC',
                'nombre_cuenta' => 'Acme Manufacturing',
                'email_owner'   => 'owner2@demo.com',
                'plan'          => 'PRO',
                'periodicidad'  => 'MENSUAL',
                'hits_incluidos'=> 500, 'espacio_gb'=>50,
            ],
            [
                'rfc_padre'     => 'QWER9202029Z1',
                'nombre_cuenta' => 'Servicios Nova',
                'email_owner'   => 'owner3@demo.com',
                'plan'          => 'FREE',
                'periodicidad'  => null,
                'hits_incluidos'=> 20, 'espacio_gb'=>1,
            ],
        ];

        foreach ($cuentas as $c) {
            $id = (string) Str::ulid();
            $codigo = strtoupper(substr($c['rfc_padre'],0,3)).'-'.substr($id,-6);

            DB::connection('admin')->table('cuentas')->updateOrInsert(
                ['rfc_padre'=>$c['rfc_padre']],
                [
                    'id'=>$id,
                    'nombre_cuenta'=>$c['nombre_cuenta'],
                    'email_owner'=>$c['email_owner'],
                    'codigo_cliente'=>$codigo,
                    'plan'=>$c['plan'],
                    'periodicidad'=>$c['periodicidad'],
                    'email_verified_at'=>now(), // marcadas como verificadas para pruebas
                    'estatus'=>'activa',
                    'hits_incluidos'=>$c['hits_incluidos'],
                    'espacio_gb'=>$c['espacio_gb'],
                    'hits_consumidos'=>0,
                    'proximo_corte'=> $c['periodicidad']==='MENSUAL' ? Carbon::now()->startOfMonth()->addMonth()->toDateString() : null,
                    'created_at'=>now(),'updated_at'=>now()
                ]
            );

            // Owner (shadow en ADMIN)
            $ownerId = (string) Str::ulid();
            DB::connection('admin')->table('usuarios_cliente_shadow')->updateOrInsert(
                ['email'=>$c['email_owner']],
                [
                    'id'=>$ownerId,
                    'cuenta_id'=>$id,
                    'es_owner'=>true,
                    'nombre'=>'Owner '.explode(' ', $c['nombre_cuenta'])[0],
                    'email'=>$c['email_owner'],
                    'password_hash'=>Hash::make('P360_demo_123'),
                    'codigo_cliente'=>$codigo,
                    'activo'=>true,
                    'created_at'=>now(),'updated_at'=>now()
                ]
            );

            // OUTBOX → upsert en CLIENTE (cuenta_config + usuario owner)
            DB::connection('admin')->table('sync_outbox')->insert([
                'id'=>Str::ulid(),
                'entidad'=>'cuenta',
                'entidad_id'=>$id,
                'operacion'=>'upsert',
                'payload'=>json_encode([
                    'cuenta_config'=>[
                        'cuenta_id'=>$id, 'codigo_cliente'=>$codigo,
                        'plan'=>$c['plan'], 'periodicidad'=>$c['periodicidad'],
                        'estatus'=>'activa',
                        'hits_incluidos'=>$c['hits_incluidos'],
                        'hits_consumidos'=>0,
                        'espacio_gb'=>$c['espacio_gb'],
                        'proximo_corte'=> $c['periodicidad']==='MENSUAL' ? Carbon::now()->startOfMonth()->addMonth()->toDateString() : null,
                    ],
                    'owner'=>[
                        'id'=>$ownerId, 'cuenta_id'=>$id,
                        'es_owner'=>true, 'nombre'=>'Owner '.explode(' ', $c['nombre_cuenta'])[0],
                        'email'=>$c['email_owner'], 'password_plain'=>'P360_demo_123',
                        'codigo_cliente'=>$codigo, 'activo'=>true
                    ]
                ]),
                'created_at'=>now(),'updated_at'=>now()
            ]);
        }
    }
}
