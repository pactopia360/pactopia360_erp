<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class Perfil extends Model
{
    protected $table = 'perfiles';

    protected $fillable = [
        'clave', 'nombre', 'descripcion', 'activo',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Para que el form (admin.crud.form) muestre seleccionados los permisos,
     * exponemos un "atributo" permisos que devuelve array de IDs.
     */
    public function getPermisosAttribute(): array
    {
        try {
            return DB::table('perfil_permiso')
                ->where('perfil_id', $this->id)
                ->pluck('permiso_id')
                ->map(fn($v)=> (int)$v)
                ->all();
        } catch (\Throwable $e) {
            return [];
        }
    }
}
