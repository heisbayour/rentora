<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Public images (logos, unit photos) on the "uploads" disk, which is
 * public/uploads: shared hosts cannot symlink storage. Names are random UUIDs.
 */
class PublicUploads
{
    public const DISK = 'uploads';

    /** Returns the path relative to public/uploads, for example "logos/{uuid}.jpg". */
    public function storeImage(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'jpg');
        $extension = $extension === 'jpeg' ? 'jpg' : $extension;
        $path = trim($directory, '/').'/'.Str::uuid()->toString().'.'.$extension;

        Storage::disk(self::DISK)->putFileAs(dirname($path), $file, basename($path));

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path !== null && $path !== '') {
            Storage::disk(self::DISK)->delete($path);
        }
    }
}
