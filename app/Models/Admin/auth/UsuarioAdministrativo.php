<?php

namespace App\Models\Admin\Auth;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class UsuarioAdministrativo extends Authenticatable
{
    use Notifiable;

    /**
     * Conexión por defecto para admins.
     * Si la conexión 'mysql_admin' no existe en config/database.php,
     * Eloquent usará el valor retornado por getConnectionName() (abajo).
     */
    protected $connection = 'mysql_admin';

    /**
     * Tabla asociada.
     */
    protected $table = 'usuario_administrativos';

    /**
     * Asignación masiva permitida.
     */
    protected $fillable = [
        'nombre',
        'email',
        'password',
        'rol',                    // <-- agregado
        'activo',
        'es_superadmin',
        'force_password_change',  // <-- agregado
        'last_login_at',
        'last_login_ip',
        'remember_token',
    ];

    /**
     * Atributos ocultos en arrays/JSON.
     */
    protected $hidden = ['password', 'remember_token'];

    /**
     * Casts de atributos.
     */
    protected $casts = [
        'activo'               => 'boolean',
        'es_superadmin'        => 'boolean',
        'force_password_change'=> 'boolean', // <-- agregado
        'last_login_at'        => 'datetime',
        // Siempre que se asigne password, se guardará hasheado (Laravel 10+).
        'password'             => 'hashed',
    ];

    /**
     * Conexión efectiva. Si no existe 'mysql_admin', cae a la default.
     */
    public function getConnectionName()
    {
        return config('database.connections.mysql_admin')
            ? 'mysql_admin'
            : (config('database.default') ?? 'mysql');
    }

    /**
     * Accessor moderno: si no existe 'name', usa 'nombre' como fallback.
     * Permite $user->name en vistas/controladores.
     */
    protected function name(): Attribute
    {
        return Attribute::get(function ($value, array $attributes) {
            return $value ?: ($attributes['nombre'] ?? null);
        });
    }

    /**
     * Normaliza email a minúsculas al asignar.
     */
    protected function email(): Attribute
    {
        return Attribute::make(
            set: fn ($value) => is_string($value) ? mb_strtolower(trim($value)) : $value,
        );
    }

    /**
     * Scope: solo activos.
     */
    public function scopeActive($q)
    {
        return Schema::hasColumn($this->getTable(), 'activo')
            ? $q->where('activo', 1)
            : $q;
    }

    /**
     * Scope: por email.
     */
    public function scopeEmail($q, string $email)
    {
        return $q->where('email', mb_strtolower($email));
    }

    /**
     * Marca último login (fecha + IP). Seguro ante ausencia de columnas.
     */
    public function markLastLogin(?string $ip = null): void
    {
        $data = [];
        if (Schema::hasColumn($this->getTable(), 'last_login_at')) {
            $data['last_login_at'] = now();
        }
        if ($ip && Schema::hasColumn($this->getTable(), 'last_login_ip')) {
            $data['last_login_ip'] = $ip;
        }
        if ($data) {
            $this->forceFill($data)->saveQuietly();
        }
    }

    /**
     * Cache interno de permisos calculados en la request.
     */
    protected array $permCache = [];

    /**
     * Verifica si el usuario posee un permiso lógico ($clave).
     * Compatible con:
     *  - usuario_perfil (usuario_id, perfil_id)
     *  - perfil_permiso (perfil_id, permiso_id)
     *  - permisos (id, clave?, nombre?)
     *
     * Reglas:
     *  - superadmin => true
     *  - si no existen las tablas/columnas, fallback = false
     */
    public function hasPerm(string $clave): bool
    {
        // Superadmin accede a todo
        if (!empty($this->es_superadmin)) {
            return true;
        }

        $key = strtolower(trim($clave));
        if ($key === '') {
            return false;
        }

        // Cache por request
        if (array_key_exists($key, $this->permCache)) {
            return $this->permCache[$key];
        }

        // Verifica existencia de tablas requeridas
        if (
            !Schema::hasTable('usuario_perfil') ||
            !Schema::hasTable('perfil_permiso') ||
            !Schema::hasTable('permisos')
        ) {
            return $this->permCache[$key] = false;
        }

        // Determina columnas presentes en 'permisos'
        $hasClave  = Schema::hasColumn('permisos', 'clave');
        $hasNombre = Schema::hasColumn('permisos', 'nombre');

        // Si no hay ni clave ni nombre, no podemos resolver
        if (!$hasClave && !$hasNombre) {
            return $this->permCache[$key] = false;
        }

        // Construye la consulta de existencia
        $q = DB::connection($this->getConnectionName())
            ->table('usuario_perfil')
            ->join('perfil_permiso', 'usuario_perfil.perfil_id', '=', 'perfil_permiso.perfil_id')
            ->join('permisos', 'perfil_permiso.permiso_id', '=', 'permisos.id')
            ->where('usuario_perfil.usuario_id', $this->getKey());

        if ($hasClave && $hasNombre) {
            $q->where(function ($w) use ($key) {
                $w->whereRaw('LOWER(permisos.clave) = ?', [$key])
                  ->orWhereRaw('LOWER(permisos.nombre) = ?', [$key]);
            });
        } elseif ($hasClave) {
            $q->whereRaw('LOWER(permisos.clave) = ?', [$key]);
        } else { // solo nombre
            $q->whereRaw('LOWER(permisos.nombre) = ?', [$key]);
        }

        return $this->permCache[$key] = $q->exists();
    }
}
