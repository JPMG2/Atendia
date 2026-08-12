<?php

declare(strict_types=1);

namespace App\Services;

use Closure;

/**
 * Quién es el negocio actual.
 *
 * Por defecto sale del usuario logueado. Se puede fijar a mano para los
 * contextos donde NO hay sesión —colas, comandos, seeders—, que es justo donde
 * el aislamiento se rompe si nadie se acuerda.
 *
 * Es un singleton del contenedor, no estático: el contenedor se reconstruye
 * entre tests, así que un negocio fijado en un test no se filtra al siguiente.
 *
 * `null` significa "sin negocio" y desactiva el filtro. Eso es correcto para el
 * admin (el dueño de AtendIa no es inquilino de nadie) y para los procesos de
 * fondo, que tienen que poder tocar los datos de cualquiera.
 */
class Tenant
{
    private ?int $businessId = null;

    /**
     * Un id fijado a mano gana sobre el usuario logueado. La bandera es
     * necesaria porque fijar `null` es una orden válida ("actuá como admin"),
     * distinta de "no fijé nada".
     */
    private bool $overridden = false;

    public function id(): ?int
    {
        if ($this->overridden) {
            return $this->businessId;
        }

        return auth()->user()?->business_id;
    }

    public function set(?int $businessId): void
    {
        $this->businessId = $businessId;
        $this->overridden = true;
    }

    public function forget(): void
    {
        $this->businessId = null;
        $this->overridden = false;
    }

    /**
     * Corre el callback en el contexto de un negocio y restaura lo anterior,
     * incluso si el callback explota. Para jobs: `Tenant::for($id, fn () => ...)`.
     */
    public function for(?int $businessId, Closure $callback): mixed
    {
        $previousId = $this->businessId;
        $previousOverridden = $this->overridden;

        $this->set($businessId);

        try {
            return $callback();
        } finally {
            $this->businessId = $previousId;
            $this->overridden = $previousOverridden;
        }
    }
}
