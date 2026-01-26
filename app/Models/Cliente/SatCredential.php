<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

class SatCredential extends Model
{
    use HasFactory;

    protected $connection = 'mysql_clientes';
    protected $table = 'sat_credentials';

    protected $primaryKey = 'id';
    public $incrementing  = false;
    protected $keyType    = 'string';

    /**
     * Fillable base (se filtra por columnas reales en DB en getFillable()).
     * Esto evita “Unknown column” cuando prod/local difieren.
     */
    protected $fillable = [
        'id',
        'cuenta_id',
        'account_id',
        'rfc',
        'razon_social',
        'cer_path',
        'key_path',
        'key_password',      // existe (TEXT)
        'key_password_enc',  // opcional (si algún día la agregas)
        'meta',
        'validated_at',      // opcional (si algún día la agregas)
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'meta'          => 'array',
        'validated_at'  => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    /** Cache por request para no consultar Schema repetidamente */
    protected static array $colsCache = [];

    /**
     * Retorna columnas reales de la tabla (cacheado).
     */
    protected function tableColumns(): array
    {
        $key = ($this->getConnectionName() ?: 'default') . '|' . $this->getTable();

        if (isset(static::$colsCache[$key])) {
            return static::$colsCache[$key];
        }

        try {
            $cols = Schema::connection($this->getConnectionName())->getColumnListing($this->getTable());
            $cols = is_array($cols) ? $cols : [];
        } catch (\Throwable) {
            $cols = [];
        }

        return static::$colsCache[$key] = $cols;
    }

    /**
     * Filtra fillable según columnas reales.
     * Si no podemos leer schema (p.ej. permisos), regresamos fillable tal cual.
     */
    public function getFillable(): array
    {
        $base = parent::getFillable();

        $cols = $this->tableColumns();
        if (empty($cols)) return $base;

        $colSet = array_fill_keys($cols, true);

        return array_values(array_filter($base, fn ($f) => isset($colSet[$f])));
    }

    /**
     * Filtra casts según columnas reales (evita que Laravel “asuma” columnas inexistentes).
     */
    public function getCasts(): array
    {
        $casts = parent::getCasts();

        $cols = $this->tableColumns();
        if (empty($cols)) return $casts;

        $colSet = array_fill_keys($cols, true);

        foreach (array_keys($casts) as $k) {
            if (!isset($colSet[$k])) unset($casts[$k]);
        }

        return $casts;
    }

    protected static function booted(): void
    {
        static::creating(function (self $m) {
            // UUID
            if (!$m->getKey()) {
                $m->setAttribute($m->getKeyName(), (string) Str::uuid());
            }

            // Normaliza RFC
            if (!empty($m->rfc)) {
                $m->rfc = strtoupper(trim((string) $m->rfc));
            }
        });

        static::saving(function (self $m) {
            if (!empty($m->rfc)) {
                $m->rfc = strtoupper(trim((string) $m->rfc));
            }
        });
    }

    /**
     * Helper: asigna password cifrado de forma compatible con tu DB real.
     * - Si existe key_password_enc: guarda ahí
     * - Si no: guarda en key_password (cifrado)
     */
    public function setEncryptedKeyPassword(string $plain): void
    {
        try {
            $enc = encrypt($plain);
        } catch (\Throwable) {
            $enc = base64_encode($plain);
        }

        $cols = $this->tableColumns();
        $has  = function (string $c) use ($cols): bool {
            return in_array($c, $cols, true);
        };

        if ($has('key_password_enc')) {
            $this->key_password_enc = $enc;
            if ($has('key_password')) $this->key_password = null;
            return;
        }

        if ($has('key_password')) {
            $this->key_password = $enc;
            return;
        }

        // último recurso
        $meta = is_array($this->meta) ? $this->meta : [];
        $meta['key_password_enc'] = $enc;
        $this->meta = $meta;
    }

    // -----------------------------
    // Scopes / helpers
    // -----------------------------

    public function scopeForCuenta($q, ?string $cuentaId)
    {
        if ($cuentaId !== null && $cuentaId !== '') {
            $q->where('cuenta_id', $cuentaId);
        }
        return $q;
    }

    public function scopeRfc($q, ?string $rfc)
    {
        if ($rfc !== null && $rfc !== '') {
            $q->where('rfc', strtoupper(trim($rfc)));
        }
        return $q;
    }

    public static function rfcsForCuenta(string $cuentaId): Collection
    {
        return static::query()
            ->where('cuenta_id', $cuentaId)
            ->pluck('rfc')
            ->filter(fn ($r) => filled($r))
            ->map(fn ($r) => strtoupper(trim((string) $r)))
            ->unique()
            ->values();
    }

    public function getHasFilesAttribute(): bool
    {
        return filled($this->cer_path) && filled($this->key_path);
    }
}
