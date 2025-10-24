<?php

namespace App\Support\Auth;

/**
 * Desactiva por completo el "remember me" a nivel de modelo.
 * - Ignora lecturas/escrituras al campo remember_token.
 * - Reporta nombre de token vacío para que los guards no lo intenten usar.
 */
trait WithoutRememberToken
{
    /** Nunca devolver un token almacenado */
    public function getRememberToken()
    {
        return null;
    }

    /** Ignorar cualquier intento de setear el token */
    public function setRememberToken($value): void
    {
        // no-op
    }

    /** Hacer que el ORM/Guard crean que no hay columna para token */
    public function getRememberTokenName()
    {
        return '';
    }

    /**
     * Protección adicional: si alguien intenta hacer ->remember_token = ...
     * lo ignoramos en el setAttribute del modelo.
     */
    public function setAttribute($key, $value)
    {
        if ($key === 'remember_token') {
            return $this; // no-op
        }
        return parent::setAttribute($key, $value);
    }
}
