<?php

declare(strict_types=1);

namespace App\Models\Cliente;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Schema;

class SatDownload extends Model
{
    use HasFactory;

    protected $connection = 'mysql_clientes';
    protected $table      = 'sat_downloads';

    // UUID string
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'cuenta_id',
        'rfc',
        'tipo',          // 'emitidos' | 'recibidos'
        'date_from',
        'date_to',
        'status',        // 'pending' | 'processing' | 'ready' | 'done' | 'listo' | 'canceled'
        'request_id',
        'package_id',
        'zip_path',
        'error_message',
        'expires_at',
        'auto',

        // métricas
        'xml_count',
        'total_xml',
        'size_gb',
        'size_bytes',
        'bytes',
        'peso_gb',
        'tam_gb',
        'time_left',
        'disponible_hasta',
        'costo',
        'discount_pct',
        'descuento_pct',

        // pago / stripe
        'stripe_session_id',
        'paid_at',
        'canceled_at',

        // si existen en tu tabla, los puedes dejar (no afectan)
        'meta_json',
        'meta',
        'payload_json',
        'payload',
        'response_json',
        'response',
    ];

    protected $casts = [
        'date_from'   => 'datetime',
        'date_to'     => 'datetime',
        'expires_at'  => 'datetime',
        'paid_at'     => 'datetime',
        'canceled_at' => 'datetime',

        // IMPORTANTES: estandarizar jsons a array
        'meta'         => 'array',
        'meta_json'    => 'array',
        'payload'      => 'array',
        'payload_json' => 'array',
        'response'     => 'array',
        'response_json'=> 'array',

        // métricas útiles (opcional pero recomendado)
        'xml_count'   => 'integer',
        'total_xml'   => 'integer',
        'size_bytes'  => 'integer',
        'bytes'       => 'integer',
        'costo'       => 'float',
        'discount_pct'=> 'float',
        'descuento_pct'=> 'float',
    ];


    protected static function booted(): void
    {
        static::creating(function (self $m) {
            self::syncPkDefinition($m);

            if (!$m->getKey() && $m->keyType === 'string' && $m->incrementing === false) {
                $m->setAttribute($m->getKeyName(), (string) Str::uuid());
            }

            $m->status = $m->status ?: 'pending';
        });

        static::retrieved(function (self $m) {
            self::syncPkDefinition($m);
        });
    }

    private static function syncPkDefinition(self $m): void
    {
        try {
            $conn = $m->getConnectionName();
            $tbl  = $m->getTable();
            $type = Schema::connection($conn)->getColumnType($tbl, $m->getKeyName());

            if (in_array($type, ['integer','bigint','mediumint','smallint','tinyint'], true)) {
                $m->incrementing = true;
                $m->keyType      = 'int';
            } else {
                $m->incrementing = false;
                $m->keyType      = 'string';
            }
        } catch (\Throwable $e) {
            // mantener defaults
        }
    }

    public function isPending(): bool
    {
        return in_array($this->status, ['pending','processing'], true);
    }

    public function isReady(): bool
    {
        return in_array($this->status, ['ready','done','listo'], true);
    }

    public function isPaid(): bool
    {
        return !is_null($this->paid_at);
    }

    /**
     * Si lo usas en algún flujo de “linking”, déjalo aquí.
     */
    public function resolveLinkedDownloadIdFromSatDownload(self $dl): ?string
    {
        $candidates = [];

        foreach (['meta_json','meta','payload_json','payload','response_json','response'] as $col) {
            if (!isset($dl->{$col})) continue;
            $raw = (string) $dl->{$col};
            if ($raw === '') continue;

            $arr = json_decode($raw, true);
            if (!is_array($arr)) continue;

            foreach (['linked_download_id','new_download_id','download_id'] as $k) {
                $v = $arr[$k] ?? null;
                if (is_string($v) && preg_match('~^[0-9a-f\-]{36}$~i', $v)) {
                    $candidates[] = $v;
                }
            }

            foreach (['payload.download_id','payload.linked_download_id','link.new','linked.new','linked.download_id'] as $p) {
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
