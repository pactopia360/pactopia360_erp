<?php

namespace App\Policies;

use App\Models\Pago;
use App\Policies\Concerns\MapsPerms;

class PagoPolicy
{
    use MapsPerms;

    public function viewAny($user): bool { return $this->allow($user, 'pagos.ver'); }
    public function view($user, Pago $m): bool { return $this->allow($user, 'pagos.ver'); }
    public function create($user): bool { return $this->allow($user, 'pagos.crear'); }
    public function update($user, Pago $m): bool { return $this->allow($user, 'pagos.editar'); }
    public function delete($user, Pago $m): bool { return $this->allow($user, 'pagos.eliminar'); }
}
