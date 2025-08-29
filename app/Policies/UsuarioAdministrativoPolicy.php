<?php

namespace App\Policies;

use App\Models\Admin\Auth\UsuarioAdministrativo;
use App\Policies\Concerns\MapsPerms;

class UsuarioAdministrativoPolicy
{
    use MapsPerms;

    public function viewAny($user): bool { return $this->allow($user, 'usuarios_admin.ver'); }
    public function view($user, UsuarioAdministrativo $m): bool { return $this->allow($user, 'usuarios_admin.ver'); }
    public function create($user): bool { return $this->allow($user, 'usuarios_admin.crear'); }
    public function update($user, UsuarioAdministrativo $m): bool { return $this->allow($user, 'usuarios_admin.editar'); }
    public function delete($user, UsuarioAdministrativo $m): bool { return $this->allow($user, 'usuarios_admin.eliminar'); }
}
