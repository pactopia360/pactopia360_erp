<?php

namespace App\Models\Empresas\Pactopia360\CRM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Carrito extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'crm_carritos';
    protected $guarded = [];

    /**
     * Estados válidos usados en formularios/filtros.
     * (Alineado con el controlador y el FormRequest)
     */
    public const ESTADOS = ['abierto', 'convertido', 'cancelado'];

    /**
     * Casts de columnas JSON/numéricas/fechas.
     */
    protected $casts = [
        'total'      => 'decimal:2',
        'etiquetas'  => 'array',
        'meta'       => 'array',
        'metadata'   => 'array', // por compatibilidad si existe esta columna en BD
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Título legible para listar.
     */
    public function getDisplayTitleAttribute(): string
    {
        $id = $this->attributes['id'] ?? null;

        return $this->attributes['titulo']
            ?? $this->attributes['cliente']
            ?? $this->attributes['email']
            ?? ($id ? ('Carrito #'.$id) : 'Carrito');
    }
}
