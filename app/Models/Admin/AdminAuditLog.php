<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class AdminAuditLog extends BaseAdminModel
{
    protected $connection = 'mysql_admin';
    protected $table = 'admin_audit_logs';

    protected $fillable = [
        'actor_id', 'target_type', 'target_id', 'action',
        'changes', 'ip', 'user_agent',
    ];

    protected $casts = [
        'changes' => 'array',
    ];

    public static function log(?int $actorId, string $action, string $targetType, ?int $targetId = null, array $changes = []): void
    {
        try {
            static::create([
                'actor_id'    => $actorId,
                'target_type' => $targetType,
                'target_id'   => $targetId,
                'action'      => $action,
                'changes'     => $changes ?: null,
                'ip'          => request()->ip(),
                'user_agent'  => substr((string) request()->userAgent(), 0, 500),
            ]);
        } catch (\Throwable $e) {
            // Evita romper el flujo si la tabla no existe aún.
            \Log::warning('[AuditLog] '.$e->getMessage());
        }
    }
}
