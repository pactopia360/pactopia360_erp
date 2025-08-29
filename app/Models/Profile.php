<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Profile extends Model
{
    protected $table = 'perfiles';
    protected $fillable = ['clave','nombre','descripcion','activo'];

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'perfil_permiso', 'perfil_id', 'permiso_id')->withTimestamps();
    }

    // Opcional: asignar perfiles a usuarios
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'perfil_usuario', 'perfil_id', 'user_id')->withTimestamps();
    }
}
