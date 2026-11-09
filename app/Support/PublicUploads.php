<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Public images (logos, cover images, unit photos) on the "uploads" disk,
 * which is public/uploads: shared hosts cannot symlink storage. Names are
 * random UUIDs.
 */
class PublicUploads
{
    public const DISK = 'uploads';

    private const MAX_EDGE = 1600;

    private const JPEG_QUALITY = 80;

    /** Stores the file as uploaded. Returns the path relative to public/uploads, for example "logos/{uuid}.png". */
    public function storeImage(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk(self::DISK)->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    /**
     * Re-encodes a photo as JPEG, at most 1600px on its longest side. The
     * browser already compresses (x-file-dropzone); doing it again here
     * guarantees the {uuid}.jpg format and strips EXIF data such as GPS.
     */
    public function storeJpeg(UploadedFile $file, string $directory): string
    {
        $source = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($source === false) {
            throw new RuntimeException('The image could not be read.');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, self::MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $scale));
        $targetHeight = max(1, (int) round($height * $scale));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        ob_start();
        imagejpeg($canvas, null, self::JPEG_QUALITY);
        $jpeg = (string) ob_get_clean();
        imagedestroy($canvas);
        imagedestroy($source);

        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.jpg';
        Storage::disk(self::DISK)->put($path, $jpeg);

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
