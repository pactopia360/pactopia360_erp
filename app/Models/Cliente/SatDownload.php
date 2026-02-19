<?php

declare(strict_types=1);

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * SatDownload (mysql_clientes.sat_downloads)
 *
 * - PK puede ser UUID (char(36)) o int, auto-detect por esquema.
 * - Casts JSON a array (meta/payload/response) para uso consistente.
 * - Helpers: isPending/isReady/isPaid + expiración + estado normalizado.
 */
class SatDownload extends Model
{
    use HasFactory;

    protected $connection = 'mysql_clientes';
    protected $table      = 'sat_downloads';

    // Defaults: UUID string (se ajusta en runtime si la PK es numérica)
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'cuenta_id',
        'rfc',
        'tipo',          // 'emitidos' | 'recibidos' | 'ambos' (según tu flujo)
        'date_from',
        'date_to',
        'status',        // 'pending' | 'processing' | 'ready' | 'done' | 'listo' | 'canceled' | etc.
        'request_id',
        'package_id',
        'zip_path',
        'zip_disk',
        'vault_path',
        'error_message',
        'expires_at',
        'auto',

        // métricas
        'xml_count',
        'total_xml',
        'cfdi_count',
        'size_gb',
        'size_mb',
        'size_bytes',
        'zip_bytes',
        'bytes',
        'peso_gb',
        'tam_gb',
        'time_left',
        'disponible_hasta',
        'costo',
        'subtotal',
        'iva',
        'total',
        'vigencia',
        'discount_pct',
        'descuento_pct',

        // tracking
        'last_checked_at',
        'finished_at',
        'downloaded_at',
        'attempts',

        // pago / stripe
        'stripe_session_id',
        'paid_at',
        'canceled_at',

        // json blobs (si existen en tu tabla, no afectan)
        'meta_json',
        'meta',
        'payload_json',
        'payload',
        'response_json',
        'response',
    ];

    protected $casts = [
        // fechas
        'date_from'       => 'datetime',
        'date_to'         => 'datetime',
        'expires_at'      => 'datetime',
        'paid_at'         => 'datetime',
        'canceled_at'     => 'datetime',
        'last_checked_at' => 'datetime',
        'finished_at'     => 'datetime',
        'downloaded_at'   => 'datetime',

        // IMPORTANTES: estandarizar jsons a array
        'meta'          => 'array',
        'meta_json'     => 'array',
        'payload'       => 'array',
        'payload_json'  => 'array',
        'response'      => 'array',
        'response_json' => 'array',

        // métricas
        'xml_count'      => 'integer',
        'total_xml'      => 'integer',
        'cfdi_count'     => 'integer',
        'size_bytes'     => 'integer',
        'zip_bytes'      => 'integer',
        'bytes'          => 'integer',
        'size_mb'        => 'float',
        'size_gb'        => 'float',
        'costo'          => 'float',
        'subtotal'       => 'float',
        'iva'            => 'float',
        'total'          => 'float',
        'discount_pct'   => 'float',
        'descuento_pct'  => 'float',
        'attempts'       => 'integer',
        'auto'           => 'integer',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $m): void {
            self::syncPkDefinition($m);

            // Si PK es string (UUID) y no viene, genera UUID.
            if (!$m->getKey() && $m->keyType === 'string' && $m->incrementing === false) {
                $m->setAttribute($m->getKeyName(), (string) Str::uuid());
            }

            // Default status
            $m->status = (string) ($m->status ?: 'pending');

            // Normaliza RFC en insert (si viene)
            if (!empty($m->rfc)) {
                $m->rfc = strtoupper(preg_replace('/\s+/', '', (string) $m->rfc) ?: (string) $m->rfc);
            }

            // Normaliza tipo (si viene)
            if (isset($m->tipo) && is_string($m->tipo)) {
                $m->tipo = strtolower(trim($m->tipo));
            }
        });

        static::retrieved(function (self $m): void {
            self::syncPkDefinition($m);
        });

        static::saving(function (self $m): void {
            // defensivo: evita RFC con espacios
            if (!empty($m->rfc)) {
                $m->rfc = strtoupper(preg_replace('/\s+/', '', (string) $m->rfc) ?: (string) $m->rfc);
            }
            if (isset($m->tipo) && is_string($m->tipo)) {
                $m->tipo = strtolower(trim($m->tipo));
            }
        });
    }

    /**
     * Auto-detecta el tipo de PK para compat con entornos donde id es int.
     */
    private static function syncPkDefinition(self $m): void
    {
        try {
            $conn = $m->getConnectionName();
            $tbl  = $m->getTable();
            $pk   = $m->getKeyName();

            $type = Schema::connection($conn)->getColumnType($tbl, $pk);

            if (in_array($type, ['integer', 'bigint', 'mediumint', 'smallint', 'tinyint'], true)) {
                $m->incrementing = true;
                $m->keyType      = 'int';
            } else {
                $m->incrementing = false;
                $m->keyType      = 'string';
            }
        } catch (\Throwable) {
            // mantener defaults
        }
    }

    // =========================================================
    // Status helpers
    // =========================================================

    public function isPending(): bool
    {
        $s = $this->statusNormalized();
        return in_array($s, ['pending', 'processing', 'requested', 'created'], true);
    }

    public function isReady(): bool
    {
        $s = $this->statusNormalized();
        return in_array($s, ['ready', 'done', 'listo', 'completed', 'finalizado', 'finished', 'terminado', 'downloaded', 'paid'], true);
    }

    public function isPaid(): bool
    {
        return !is_null($this->paid_at);
    }

    public function isExpired(): bool
    {
        if ($this->isPaid()) return false;

        $exp = $this->expires_at;
        if ($exp instanceof Carbon) {
            return $exp->isPast();
        }

        // fallback por "disponible_hasta" si existe (algunos entornos)
        $raw = $this->disponible_hasta ?? null;
        if ($raw) {
            try {
                return Carbon::parse((string) $raw)->isPast();
            } catch (\Throwable) {
                return false;
            }
        }

        return false;
    }

    /**
     * Normaliza status a minúsculas y tolera columnas/valores heterogéneos.
     */
    public function statusNormalized(): string
    {
        $raw = (string) ($this->status ?? $this->estado ?? $this->sat_status ?? '');
        $raw = strtolower(trim($raw));
        $raw = preg_replace('/\s+/', '_', $raw) ?: $raw;

        // alias comunes
        $map = [
            'en_proceso' => 'processing',
            'process'    => 'processing',
            'requested'  => 'requested',
            'solicitud'  => 'requested',
            'request'    => 'requested',

            'listo'      => 'ready',
            'ready'      => 'ready',

            'done'       => 'done',
            'completed'  => 'done',
            'finalizado' => 'done',
            'finished'   => 'done',
            'terminado'  => 'done',

            'downloaded' => 'downloaded',
            'paid'       => 'paid',
            'pagado'     => 'paid',

            'error'      => 'error',
            'failed'     => 'error',
            'cancelled'  => 'canceled',
            'canceled'   => 'canceled',
            'cancelado'  => 'canceled',

            'expirada'   => 'expired',
            'expired'    => 'expired',
        ];

        return $map[$raw] ?? $raw;
    }

    // =========================================================
    // Meta helpers
    // =========================================================

    public function metaGet(string $key, $default = null)
    {
        $meta = $this->meta;
        if (!is_array($meta)) $meta = [];
        return data_get($meta, $key, $default);
    }

    public function metaSet(string $key, $value): void
    {
        $meta = $this->meta;
        if (!is_array($meta)) $meta = [];
        data_set($meta, $key, $value);
        $this->meta = $meta;
    }

    // =========================================================
    // Linking helper (FIX: soporta array y JSON string)
    // =========================================================

    /**
     * Resuelve un download_id "linkeado" desde meta/payload/response.
     * Útil cuando el servicio crea un registro nuevo y deja referencia al anterior.
     */
    public function resolveLinkedDownloadIdFromSatDownload(self $dl): ?string
    {
        $candidates = [];

        foreach (['meta_json', 'meta', 'payload_json', 'payload', 'response_json', 'response'] as $col) {
            if (!isset($dl->{$col})) continue;

            $raw = $dl->{$col};

            // ✅ Con casts, puede ser array ya.
            if (is_array($raw)) {
                $arr = $raw;
            } elseif (is_string($raw)) {
                $raw = trim($raw);
                if ($raw === '') continue;

                $tmp = json_decode($raw, true);
                if (!is_array($tmp)) continue;

                $arr = $tmp;
            } else {
                continue;
            }

            foreach (['linked_download_id', 'new_download_id', 'download_id'] as $k) {
                $v = $arr[$k] ?? null;
                if (is_string($v) && preg_match('~^[0-9a-f\-]{36}$~i', $v)) {
                    $candidates[] = $v;
                }
            }

            foreach ([
                'payload.download_id',
                'payload.linked_download_id',
                'link.new',
                'linked.new',
                'linked.download_id',
            ] as $p) {
                $v = data_get($arr, $p);
                if (is_string($v) && preg_match('~^[0-9a-f\-]{36}$~i', $v)) {
                    $candidates[] = $v;
                }
            }
        }

        $candidates = array_values(array_unique($candidates));
        foreach ($candidates as $id) {
            if ($id !== (string) $dl->id) return $id;
        }

        return null;
    }
}