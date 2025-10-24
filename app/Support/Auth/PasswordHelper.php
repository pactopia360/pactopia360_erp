<?php

namespace App\Support\Auth;

use Illuminate\Support\Facades\Hash;

class PasswordHelper
{
    /**
     * Asigna una nueva contraseña segura (bcrypt) a un usuario de clientes.
     */
    public static function setClientePassword(string $userId, string $plain): bool
    {
        try {
            \DB::connection('mysql_clientes')
                ->table('usuarios_cuenta')
                ->where('id', $userId)
                ->update([
                    'password'   => Hash::make($plain),
                    'updated_at' => now(),
                ]);
            return true;
        } catch (\Throwable $e) {
            \Log::error('Error asignando password a usuario cliente', [
                'user_id' => $userId,
                'error'   => $e->getMessage(),
            ]);
            return false;
        }
    }
}
