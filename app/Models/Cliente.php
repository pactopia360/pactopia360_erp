<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

class Cliente extends Model
{
    /**
     * Si tus tablas de clientes viven en el connection "mysql_clientes",
     * dejamos esto fijo para que no tengas que poner ->on('mysql_clientes') en todos lados.
     * Si no fuera así, borra esta línea.
     */
    protected $connection = 'mysql_clientes';

    protected $table = 'clientes';

    protected $fillable = [
        'razon_social',
        'nombre_comercial',
        'rfc',
        'plan_id',
        'plan',
        'activo',
        // si existe en tu esquema:
        'cuenta_id',
    ];

    protected $casts = [
        'activo' => 'boolean',
    ];

    /**
     * Relaciones
     */
    public function plan()
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    // Si manejas receptores asociados a este cliente (ajusta nombre/clave foránea si aplica)
    public function receptores()
    {
        return $this->hasMany(Receptor::class, 'cliente_id');
    }

    // Si manejas CFDIs desde aquí (opcional)
    public function cfdis()
    {
        return $this->hasMany(Cfdi::class, 'cliente_id');
    }

    /**
     * Accessors / Helpers
     */
    public function getDisplayNameAttribute(): string
    {
        return $this->nombre_comercial ?: ($this->razon_social ?: ('#'.$this->id));
    }

    /**
     * Scopes reutilizables
     */

    // Búsqueda simple por nombre o RFC
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }

        return $q->where(function (Builder $w) use ($term) {
            $w->where('razon_social', 'like', "%{$term}%")
              ->orWhere('nombre_comercial', 'like', "%{$term}%")
              ->orWhere('rfc', 'like', "%{$term}%");
        });
    }

    // Filtrar por cuenta (si la columna existe en tu esquema)
    public function scopeForCuenta(Builder $q, $cuentaId): Builder
    {
        if ($cuentaId === null || $cuentaId === '') {
            return $q;
        }

        // No hacemos introspección de esquema aquí para mantener el modelo liviano.
        // Asumimos que existe `cuenta_id` cuando se use este scope.
        return $q->where('cuenta_id', $cuentaId);
    }

    // Ordenamiento canónico para listados
    public function scopeOrdered(Builder $q): Builder
    {
        return $q->orderBy('nombre_comercial')->orderBy('razon_social');
    }
}
