<?php

namespace App\Policies\Concerns;

trait MapsPerms
{
    protected function isSuper($user): bool
    {
        try {
            return (bool) ($user->es_superadmin ?? $user->getAttribute('es_superadmin') ?? false);
        } catch (\Throwable $e) {
            return false;
        }
    }

    protected function allow($user, string $perm): bool
    {
        if ($this->isSuper($user)) return true;
        try { return $user->can('perm', $perm); } catch (\Throwable $e) { return false; }
    }
}
