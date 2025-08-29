<?php

namespace App\Support\Concerns;

use App\Models\AuditLog;

trait LogsAudit
{
    protected function audit(string $action, $entity = null, array $meta = []): void
    {
        try{
            AuditLog::create([
                'user_id' => auth('admin')->id(),
                'action'  => $action,
                'entity_type' => $entity ? get_class($entity) : null,
                'entity_id'   => $entity->id ?? null,
                'meta'   => $meta,
                'ip'     => request()->ip(),
            ]);
        }catch(\Throwable $e){}
    }
}
