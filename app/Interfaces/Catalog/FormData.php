<?php

declare(strict_types=1);

namespace App\Interfaces\Catalog;

use Livewire\Wireable;

/**
 * El DTO que carga un editor de catálogo.
 *
 * `BaseCatalogForm` guarda el DTO en UNA propiedad `$data` compartida por los 9
 * maestros, así que su tipo no puede ser `CurrencyDto`: tiene que ser lo que los
 * nueve tienen en común. Esta interfaz es ese tipo, y de paso es la cerradura —
 * un DTO nuevo que se olvide `toPayload()` revienta al cargar la clase, igual
 * que [[DataTable]] del lado del modelo.
 *
 * Livewire hidrata bien igual: `WireableSynth` guarda la clase CONCRETA en el
 * metadato del snapshot, no el tipo de la propiedad.
 */
interface FormData extends Wireable
{
    /**
     * Reconstruye el DTO desde un array (el `toArray()` del modelo al editar).
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self;

    /**
     * El estado del DTO tal cual, para Livewire y para volver a armarlo.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array;

    /**
     * Lo que se manda a validar y a la Action: los mismos datos ya normalizados
     * por el modelo (mayúsculas de un código, espacios de un nombre, null de una
     * columna nullable).
     *
     * @return array<string, mixed>
     */
    public function toPayload(): array;
}
