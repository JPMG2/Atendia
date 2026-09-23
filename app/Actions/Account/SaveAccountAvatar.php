<?php

declare(strict_types=1);

namespace App\Actions\Account;

use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The profile photo, cropped to a centered square and shrunk to 256px WebP
 * on the way in: a phone photo is megabytes, the avatar it becomes is tiny.
 * Null removes it; the replaced file is always dropped, never orphaned.
 */
class SaveAccountAvatar
{
    private const int SIZE = 256;

    public function handle(User $user, ?UploadedFile $file): User
    {
        $previous = $user->getAttributes()['avatar_path'] ?? null;

        $user->avatar_path = $file === null ? null : $this->store($user, $file);
        $user->save();

        if ($previous !== null && $previous !== $user->avatar_path) {
            Storage::disk('public')->delete($previous);
        }

        return $user;
    }

    private function store(User $user, UploadedFile $file): string
    {
        $source = imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        $source = $this->uprightFromExif($source, $file);

        $width = imagesx($source);
        $height = imagesy($source);
        $side = min($width, $height);

        $square = imagecreatetruecolor(self::SIZE, self::SIZE);
        imagealphablending($square, false);
        imagesavealpha($square, true);
        imagecopyresampled(
            $square, $source, 0, 0,
            intdiv($width - $side, 2), intdiv($height - $side, 2),
            self::SIZE, self::SIZE, $side, $side,
        );

        ob_start();
        imagewebp($square, null, 85);
        $bytes = (string) ob_get_clean();

        $path = 'avatars/'.$user->id.'-'.Str::random(16).'.webp';
        Storage::disk('public')->put($path, $bytes);

        return $path;
    }

    /** Phones store portraits sideways plus an EXIF hint; without this the face lies down. */
    private function uprightFromExif(\GdImage $image, UploadedFile $file): \GdImage
    {
        if (! in_array($file->getMimeType(), ['image/jpeg', 'image/jpg'], true)) {
            return $image;
        }

        $orientation = @exif_read_data($file->getRealPath())['Orientation'] ?? 1;
        $angle = [3 => 180, 6 => -90, 8 => 90][$orientation] ?? 0;

        return $angle === 0 ? $image : imagerotate($image, $angle, 0);
    }
}
