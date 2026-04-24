<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Model;

class Receptor extends Model
{
    protected $connection = 'mysql_clientes';
    protected $table = 'receptores';

    protected $fillable = [
        'cuenta_id',
        'rfc',
        'razon_social',
        'nombre_comercial',
        'uso_cfdi',
        'forma_pago',
        'metodo_pago',
        'regimen_fiscal',
        'codigo_postal',
        'pais',
        'estado',
        'municipio',
        'colonia',
        'calle',
        'no_ext',
        'no_int',
        'email',
        'telefono',
        'extras',
    ];

    protected $casts = [
        'cuenta_id' => 'integer',
        'extras' => 'array',
    ];

    public function getNombreFiscalAttribute(): string
    {
        return trim((string) ($this->razon_social ?: $this->nombre_comercial ?: $this->rfc));
    }

    public function getDireccionFiscalAttribute(): string
    {
        return collect([
            $this->calle,
            $this->no_ext,
            $this->no_int ? 'Int. ' . $this->no_int : null,
            $this->colonia,
            $this->municipio,
            $this->estado,
            $this->codigo_postal,
        ])
            ->filter()
            ->implode(', ');
    }
}