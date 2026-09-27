<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CatalogPhoto;
use Illuminate\Support\Facades\Storage;

/** Removes the row and both files: an orphan on a public disk is nobody's to find. */
class DeleteCatalogPhoto
{
    public function handle(CatalogPhoto $photo): void
    {
        Storage::disk($photo->disk)->delete([$photo->path, $photo->thumb_path]);
        $photo->delete();
    }
}
