<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Private files (documents, payment proofs, receipts) on the "local" disk,
 * storage/app/private. Never web accessible; served by FileController.
 */
class PrivateFiles
{
    public const DISK = 'local';

    public function store(UploadedFile $file, string $directory): string
    {
        $extension = strtolower($file->guessExtension() ?: $file->getClientOriginalExtension() ?: 'bin');
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
