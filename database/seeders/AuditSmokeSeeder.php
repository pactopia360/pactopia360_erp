<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AuditSmokeSeeder extends Seeder
{
    public function run(): void
    {
        DB::connection('mysql_admin')->table('audits')->insert([
            'id'           => (string) Str::uuid(),
            'account_id'   => 1, // puedes ajustar si tienes un account_id real
            'usuario_id'   => (string) Str::uuid(),
            'event'        => 'SMOKE_TEST',
            'rfc'          => 'TEST010101AAA',
            'razon_social' => 'Smoke Test S.A. de C.V.',
            'correo'       => 'smoke@pactopia.com',
            'plan'         => 'free',
            'ip'           => '127.0.0.1',
            'user_agent'   => 'seed',
            'meta'         => json_encode(['test' => true, 'ok' => 'Seeder AuditSmokeSeeder'], JSON_UNESCAPED_UNICODE),
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);

        $this->command->info('✅ Registro de prueba insertado en audits (mysql_admin)');
    }
}
