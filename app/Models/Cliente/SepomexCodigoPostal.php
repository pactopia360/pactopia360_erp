<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class SepomexCodigoPostal extends Model
{
    protected $connection = 'mysql_clientes';

    protected $table = 'sepomex_codigos_postales';

    protected $fillable = [
        'codigo_postal',
        'estado',
        'municipio',
        'ciudad',
        'colonia',
        'tipo_asentamiento',
        'estado_clave',
        'municipio_clave',
        'zona',
        'activo',
        'extras',
    ];

    protected $casts = [
        'activo' => 'boolean',
        'extras' => 'array',
    ];

    public function scopeActivo($query)
    {
        return $query->where('activo', true);
    }

    public function scopeCp($query, string $codigoPostal)
    {
        return $query->where('codigo_postal', preg_replace('/\D+/', '', $codigoPostal));
    }

    public function getLabelAttribute(): string
    {
        return trim(collect([
            $this->colonia,
            $this->tipo_asentamiento,
            $this->municipio,
            $this->estado,
            $this->codigo_postal,
        ])->filter()->implode(' · '));
    }
}