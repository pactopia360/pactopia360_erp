<?php

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class SatCredential extends Model
{
    use HasFactory;

    protected $connection = 'mysql_clientes';
    protected $table = 'sat_credentials';

    protected $primaryKey = 'id';
    public $incrementing  = false;   // se ajustará dinámicamente en booted()
    protected $keyType    = 'string';// se ajustará dinámicamente en booted()

    protected $fillable = [
        'id',
        'cuenta_id',
        'rfc',
        'razon_social',
        'cer_path',
        'key_path',
        'key_password',      // compat legacy
        'key_password_enc',  // oficial (cifrada)
        'meta',
        'validated_at',
        'created_at',
        'updated_at',
    ];

    protected $casts = [
        'meta'          => 'array',
        'validated_at'  => 'datetime',
        'created_at'    => 'datetime',
        'updated_at'    => 'datetime',
    ];

    protected static function booted(): void
    {
        // Detecta tipo de columna id
        static::retrieved(function (self $m) {
            self::syncPkDefinition($m);
        });

        static::creating(function (self $m) {
            self::syncPkDefinition($m);
            // Solo autogenera UUID si la PK es string (no numérica)
            if (!$m->getKey() && $m->keyType === 'string' && $m->incrementing === false) {
                $m->setAttribute($m->getKeyName(), (string) Str::uuid());
            }
            if (!empty($m->rfc)) {
                $m->rfc = strtoupper(trim($m->rfc));
            }
        });
    }

    private static function syncPkDefinition(self $m): void
    {
        try {
            $conn = $m->getConnectionName();
            $tbl  = $m->getTable();

            // getColumnType puede devolver: integer, bigint, string, guid, etc.
            $type = Schema::connection($conn)->getColumnType($tbl, $m->getKeyName());

            // Si es entero, asumimos autoincrement
            if (in_array($type, ['integer','bigint','mediumint','smallint','tinyint'], true)) {
                $m->incrementing = true;
                $m->keyType      = 'int';
            } else {
                // string/guid/varchar
                $m->incrementing = false;
                $m->keyType      = 'string';
            }
        } catch (\Throwable $e) {
            // Si no podemos detectar, mantenemos los defaults (string/uuid)
        }
    }

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
