<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CrmContacto extends Model
{
    protected $table = 'crm_contactos';

    protected $fillable = [
        'empresa_slug',
        'nombre',
        'email',
        'telefono',
        'puesto',
        'notas',
        'activo',
        'tags',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'tags'   => 'array',
    ];

    public function scopeForEmpresa($qb, string $slug)
    {
        return $qb->where('empresa_slug', $slug);
    }
}
