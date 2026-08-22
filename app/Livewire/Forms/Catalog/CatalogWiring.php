<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Interfaces\Catalog\FormData;
use Illuminate\Database\Eloquent\Model;

/**
 * Las cuatro clases con las que está cableado un maestro.
 *
 * `BaseCatalogForm` hace el mismo trabajo para los nueve; lo ÚNICO que cambia
 * es a qué DTO, qué modelo y qué Actions le habla. En vez de cuatro métodos
 * abstractos por Form, cada uno declara este objeto una sola vez y se lee de
 * corrido a qué está enchufado.
 *
 * Es también el lugar donde va a entrar la Action de baja cuando se programe:
 * un parámetro más acá, con default, y los nueve Forms siguen compilando.
 */
final readonly class CatalogWiring
{
    /**
     * @param  class-string<FormData>  $dto  El DTO que viaja en el formulario.
     * @param  class-string<Model>  $model  El modelo del maestro, para leer el registro a editar.
     * @param  class-string  $create  Action de alta: `handle(array $data): Model`.
     * @param  class-string  $update  Action de edición: `handle(int $id, array $data): Model`.
     */
    public function __construct(
        public string $dto,
        public string $model,
        public string $create,
        public string $update,
    ) {}
}
