<?php

namespace App\Models\Empresas\Pactopia360\CRM;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;

class Carrito extends Model
{
    use HasFactory, SoftDeletes;

    /** =========================
     *  Tabla y Asignación Masiva
     *  ========================= */
    protected $table = 'crm_carritos';

    // Definimos fillable explícito (más seguro que $guarded = [])
    protected $fillable = [
        'titulo',
        'estado',
        'total',
        'moneda',
        'cliente',
        'email',
        'telefono',
        'origen',
        'etiquetas',   // JSON/array
        'meta',        // JSON/array
        'metadata',    // JSON/array opcional
        'notas',
        'empresa_slug',
    ];

    /** =========================
     *  Estados válidos del flujo
     *  ========================= */
    public const ESTADOS = ['abierto', 'convertido', 'cancelado'];

    /** =========================
     *  Casts de atributos
     *  ========================= */
    protected $casts = [
        'total'      => 'decimal:2',
        'etiquetas'  => 'array',
        'meta'       => 'array',
        'metadata'   => 'array',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /** =========================
     *  Atributos calculados (JSON)
     *  ========================= */
    protected $appends = [
        'display_title',
        'total_format',
        'estado_badge',
    ];

    /** =========================
     *  Boot / Hooks del modelo
     *  ========================= */
    protected static function booted(): void
    {
        // Normalizaciones básicas antes de crear/actualizar
        static::saving(function (self $model) {
            // Normaliza moneda a 3 chars uppercase (MXN, USD...)
            if (!empty($model->moneda) && is_string($model->moneda)) {
                $model->moneda = strtoupper(substr($model->moneda, 0, 3));
            }

            // Asegura que estado sea uno permitido
            if (!empty($model->estado) && !in_array($model->estado, self::ESTADOS, true)) {
                // Opcional: asignar 'abierto' si viene inválido
                $model->estado = 'abierto';
            }

            // Limpieza de etiquetas: array plano sin vacíos
            if (!empty($model->etiquetas)) {
                $etiquetas = is_array($model->etiquetas)
                    ? $model->etiquetas
                    : array_map('trim', explode(',', (string) $model->etiquetas));

                $etiquetas = array_values(array_filter(array_map('trim', $etiquetas), static function ($v) {
                    return $v !== '' && $v !== null;
                }));

                $model->etiquetas = $etiquetas ?: null;
            }
        });
    }

    /** =========================
     *  Accessors / Presenters
     *  ========================= */

    public function getDisplayTitleAttribute(): string
    {
        $id = $this->attributes['id'] ?? null;

        return $this->attributes['titulo']
            ?? $this->attributes['cliente']
            ?? $this->attributes['email']
            ?? ($id ? ('Carrito #'.$id) : 'Carrito');
    }

    public function getTotalFormatAttribute(): string
    {
        $currency = $this->moneda ?: 'MXN';
        $value    = (float) ($this->total ?? 0);
        // Formateo simple local (usa coma para miles y dos decimales)
        return $currency.' '.number_format($value, 2, '.', ',');
    }

    public function getEstadoLabelAttribute(): string
    {
        return match ($this->estado) {
            'convertido' => 'Convertido',
            'cancelado'  => 'Cancelado',
            default      => 'Abierto',
        };
    }

    public function getEstadoBadgeAttribute(): string
    {
        // Devuelve una clase semántica para usarse en la UI (CSS)
        return match ($this->estado) {
            'convertido' => 'is-converted',
            'cancelado'  => 'is-cancelled',
            default      => 'is-open',
        };
    }

    /** =========================
     *  Helpers de estado
     *  ========================= */
    public function isAbierto(): bool
    {
        return $this->estado === 'abierto';
    }

    public function isConvertido(): bool
    {
        return $this->estado === 'convertido';
    }

    public function isCancelado(): bool
    {
        return $this->estado === 'cancelado';
    }

    /** =========================
     *  Query Scopes (coinciden con filtros del Controller)
     *  ========================= */

    /**
     * Búsqueda simple por múltiples columnas.
     */
    public function scopeSearch(Builder $q, ?string $term): Builder
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $q;
        }

        return $q->where(function ($w) use ($term) {
            $w->where('titulo', 'like', "%{$term}%")
                ->orWhere('cliente', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('telefono', 'like', "%{$term}%")
                ->orWhere('origen', 'like', "%{$term}%");
        });
    }

    /**
     * Filtra por estado válido.
     */
    public function scopeEstado(Builder $q, ?string $estado): Builder
    {
        if ($estado && in_array($estado, self::ESTADOS, true)) {
            $q->where('estado', $estado);
        }
        return $q;
    }

    /**
     * Filtra por moneda (3 letras).
     */
    public function scopeMoneda(Builder $q, ?string $moneda): Builder
    {
        if ($moneda && is_string($moneda) && strlen($moneda) >= 2) {
            $q->where('moneda', strtoupper(substr($moneda, 0, 3)));
        }
        return $q;
    }

    /**
     * Filtra por etiqueta en JSON.
     */
    public function scopeEtiqueta(Builder $q, ?string $etiqueta): Builder
    {
        if ($etiqueta === null || $etiqueta === '') {
            return $q;
        }

        // Si tu DB soporta JSON (MySQL 5.7+/MariaDB 10.2+) whereJsonContains funciona muy bien.
        // Dejamos un OR de fallback tipo LIKE para contingencias.
        return $q->where(function ($w) use ($etiqueta) {
            $w->whereJsonContains('etiquetas', $etiqueta)
              ->orWhere('etiquetas', 'like', '%"'.$etiqueta.'"%');
        });
    }

    /**
     * Rango de fechas por created_at.
     */
    public function scopeDateBetween(Builder $q, ?string $desde, ?string $hasta): Builder
    {
        if ($desde) {
            $q->whereDate('created_at', '>=', $desde);
        }
        if ($hasta) {
            $q->whereDate('created_at', '<=', $hasta);
        }
        return $q;
    }

    /**
     * Rango de totales.
     */
    public function scopeTotalBetween(Builder $q, $min = null, $max = null): Builder
    {
        if ($min !== null && $min !== '') {
            $q->where('total', '>=', (float) $min);
        }
        if ($max !== null && $max !== '') {
            $q->where('total', '<=', (float) $max);
        }
        return $q;
    }

    /**
     * Orden seguro (whitelist).
     */
    public function scopeOrderSafe(Builder $q, ?string $column = 'id', ?string $dir = 'desc'): Builder
    {
        $sortable = [
            'id', 'titulo', 'cliente', 'email', 'telefono',
            'estado', 'total', 'moneda', 'created_at', 'updated_at',
        ];

        $column = in_array($column, $sortable, true) ? $column : 'id';
        $dir    = strtolower($dir) === 'asc' ? 'asc' : 'desc';

        return $q->orderBy($column, $dir);
    }

    /** =========================
     *  Relaciones (coloca aquí cuando las tengas)
     *  ========================= */
    // public function items() { return $this->hasMany(CarritoItem::class, 'carrito_id'); }
    // public function empresa() { return $this->belongsTo(Empresa::class, 'empresa_slug', 'slug'); }
}
