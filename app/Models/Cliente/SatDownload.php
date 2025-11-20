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

    // tu id es un UUID string, no autoincrement
    public $incrementing = false;
    protected $keyType   = 'string';

    protected $fillable = [
        'id',
        'cuenta_id',
        'rfc',
        'tipo',          // 'emitidos' | 'recibidos'
        'date_from',
        'date_to',
        'status',        // 'pending' | 'processing' | 'ready' | 'done' | 'listo'
        'request_id',
        'package_id',
        'zip_path',
        'error_message',
        'expires_at',
        'auto',
    ];

    protected $casts = [
        'date_from'   => 'datetime',
        'date_to'     => 'datetime',
        'expires_at'  => 'datetime',
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
            // mantenemos defaults
        }
    }

    public function isPending(): bool { return in_array($this->status, ['pending','processing'], true); }
    public function isReady(): bool   { return in_array($this->status, ['ready','done','listo'], true); }
}
