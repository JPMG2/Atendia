<?php

declare(strict_types=1);

namespace App\Livewire\Forms\Catalog;

use App\Interfaces\Catalog\FormData;
use Illuminate\Database\Eloquent\Model;

/**
 * The four classes a master is wired to.
 *
 * `BaseCatalogForm` does the same work for the nine; the ONLY thing that
 * changes is which DTO, model and Actions it talks to. One object read at a
 * glance beats four abstract methods per Form, and the delete Action lands
 * here as one more parameter with a default.
 */
final readonly class CatalogWiring
{
    /**
     * @param  class-string<FormData>  $dto  The DTO travelling in the form.
     * @param  class-string<Model>  $model  The master's model, to read the record being edited.
     * @param  class-string  $create  Create action: `handle(array $data): Model`.
     * @param  class-string  $update  Update action: `handle(int $id, array $data): Model`.
     */
    public function __construct(
        public string $dto,
        public string $model,
        public string $create,
        public string $update,
    ) {}
}
