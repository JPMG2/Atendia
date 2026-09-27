<?php

declare(strict_types=1);

namespace App\Traits;

use GdImage;
use Illuminate\Http\UploadedFile;

/** Every photo a phone takes: loaded as GD and turned upright, or the portrait lies down. */
trait LoadsUprightImage
{
    private function uprightImage(UploadedFile $file): GdImage
    {
        $image = imagecreatefromstring((string) file_get_contents($file->getRealPath()));

        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        return $angle === 0 ? $image : imagerotate($image, $angle, 0);
    }
}
