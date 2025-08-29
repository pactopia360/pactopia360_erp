<?php

namespace App\Policies;

use App\Models\Cfdi;
use App\Policies\Concerns\MapsPerms;

class CfdiPolicy
{
    use MapsPerms;

    public function viewAny($user): bool { return $this->allow($user, 'facturacion.ver'); }
    public function view($user, Cfdi $m): bool { return $this->allow($user, 'facturacion.ver'); }
    public function create($user): bool { return $this->allow($user, 'facturacion.crear'); }
    public function update($user, Cfdi $m): bool { return $this->allow($user, 'facturacion.editar'); }
    public function delete($user, Cfdi $m): bool { return $this->allow($user, 'facturacion.eliminar'); }
}
