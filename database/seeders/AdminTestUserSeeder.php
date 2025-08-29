<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AdminTestUserSeeder extends Seeder
{
    protected string $conn = 'mysql_admin';
    protected string $table = 'usuario_administrativos';

    public function run(): void
    {
        $email = 'marco.padilla@pactopia.com';

        // Columnas opcionales según tu esquema real
        $hasActivo  = Schema::connection($this->conn)->hasColumn($this->table, 'activo');
        $hasEstatus = Schema::connection($this->conn)->hasColumn($this->table, 'estatus');

        $payload = [
            'nombre'     => 'Marco Padilla',
            'email'      => $email,
            'password'   => Hash::make('12345678'),
            'rol'        => 'admin',
            'updated_at' => now(),
        ];

        if ($hasActivo)  $payload['activo']  = 1;
        if ($hasEstatus) $payload['estatus'] = 'activo';

        $exists = DB::connection($this->conn)
            ->table($this->table)
            ->where('email', $email)
            ->first();

        if ($exists) {
            DB::connection($this->conn)
                ->table($this->table)
                ->where('email', $email)
                ->update($payload);
        } else {
            $payload['created_at'] = now();
            DB::connection($this->conn)->table($this->table)->insert($payload);
        }
    }
}
