<?php

namespace App\Policies;

use App\Models\Cliente;
use App\Policies\Concerns\MapsPerms;

class ClientePolicy
{
    use MapsPerms;

    public function viewAny($user): bool { return $this->allow($user, 'clientes.ver'); }
    public function view($user, Cliente $m): bool { return $this->allow($user, 'clientes.ver'); }
    public function create($user): bool { return $this->allow($user, 'clientes.crear'); }
    public function update($user, Cliente $m): bool { return $this->allow($user, 'clientes.editar'); }
    public function delete($user, Cliente $m): bool { return $this->allow($user, 'clientes.eliminar'); }
}
