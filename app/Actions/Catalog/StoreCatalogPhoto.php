<?php

declare(strict_types=1);

namespace App\Actions\Catalog;

use App\Models\CatalogPhoto;
use App\Models\Product;
use App\Models\Service;
use App\Traits\LoadsUprightImage;
use GdImage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Shrinks an already-moderated photo and keeps two versions: a 1080 px JPEG
 * (WhatsApp shows it sharp, it rejects WebP) and a 320 px WebP for the panel.
 * The phone original (3-5 MB) and its EXIF, GPS included, never stay.
 */
class StoreCatalogPhoto
{
    use LoadsUprightImage;

    public function handle(Product|Service $item, UploadedFile $file): CatalogPhoto
    {
        $image = $this->uprightImage($file);
        $disk = (string) config('atendia.catalog.disk');
        $base = 'businesses/'.$item->business_id.'/catalog/'.Str::uuid()->toString();

        $photo = $this->encode($this->fit($image, (int) config('atendia.catalog.max_side')), 'jpeg');
        $thumb = $this->encode($this->fit($image, (int) config('atendia.catalog.thumb_side')), 'webp');

        Storage::disk($disk)->put($base.'.jpg', $photo, 'public');
        Storage::disk($disk)->put($base.'-thumb.webp', $thumb, 'public');

        return $item->photos()->create([
            'business_id' => $item->business_id,
            'disk' => $disk,
            'path' => $base.'.jpg',
            'thumb_path' => $base.'-thumb.webp',
            'bytes' => strlen($photo) + strlen($thumb),
            'sort_order' => (int) $item->photos()->max('sort_order') + 1,
        ]);
    }

    /** Never upscales: a small photo stays small rather than blurry. */
    private function fit(GdImage $image, int $side): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $scale = min(1, $side / max($width, $height));
        $canvas = imagecreatetruecolor(max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale)));

        // JPEG has no alpha: a transparent PNG would turn black without a white floor.
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, imagesx($canvas), imagesy($canvas), $width, $height);

        return $canvas;
    }

    private function encode(GdImage $image, string $format): string
    {
        ob_start();
        $format === 'jpeg'
            ? imagejpeg($image, null, (int) config('atendia.catalog.jpeg_quality'))
            : imagewebp($image, null, 75);

        return (string) ob_get_clean();
    }
}
