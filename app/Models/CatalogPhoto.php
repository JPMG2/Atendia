<?php

declare(strict_types=1);

namespace App\Models;

use App\Traits\BelongsToBusiness;
use Database\Factories\CatalogPhotoFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Storage;

/** One catalog photo of a product or a service, stored twice: for WhatsApp and for the panel. */
#[Fillable(['business_id', 'photoable_type', 'photoable_id', 'disk', 'path', 'thumb_path', 'bytes', 'sort_order'])]
class CatalogPhoto extends Model
{
    use BelongsToBusiness;

    /** @use HasFactory<CatalogPhotoFactory> */
    use HasFactory;

    /** @return MorphTo<Model, $this> */
    public function photoable(): MorphTo
    {
        return $this->morphTo();
    }

    /** The full 1080px version, what the carousel shows. */
    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function thumbUrl(): string
    {
        return Storage::disk($this->disk)->url($this->thumb_path);
    }

    /** The WhatsApp version as raw bytes: Evolution takes base64, whatever disk it lives on. */
    public function contents(): string
    {
        return (string) Storage::disk($this->disk)->get($this->path);
    }
}
