<?php

namespace App\Policies;

use App\Models\Plan;
use App\Policies\Concerns\MapsPerms;

class PlanPolicy
{
    use MapsPerms;

    public function viewAny($user): bool { return $this->allow($user, 'planes.ver'); }
    public function view($user, Plan $m): bool { return $this->allow($user, 'planes.ver'); }
    public function create($user): bool { return $this->allow($user, 'planes.crear'); }
    public function update($user, Plan $m): bool { return $this->allow($user, 'planes.editar'); }
    public function delete($user, Plan $m): bool { return $this->allow($user, 'planes.eliminar'); }
}
