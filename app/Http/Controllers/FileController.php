<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Document;
use App\Models\MaintenanceAttachment;
use App\Support\PrivateFiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The only way to read private files (spec 11): checks the policy, then
 * streams from storage/app/private with a safe Content-Disposition.
 */
class FileController extends Controller
{
    public function maintenancePhoto(MaintenanceAttachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment->request);

        $disk = Storage::disk(PrivateFiles::DISK);
        abort_unless($disk->exists($attachment->path), 404);

        return $disk->response($attachment->path, null, [
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function document(Request $request, Document $document): StreamedResponse
    {
        $this->authorize('view', $document);

        $disk = Storage::disk(PrivateFiles::DISK);
        abort_unless($disk->exists($document->path), 404);

        $extension = pathinfo($document->path, PATHINFO_EXTENSION);
        $filename = str_ends_with(strtolower($document->name), '.'.$extension) ? $document->name : $document->name.'.'.$extension;
        $inline = $request->boolean('inline') && in_array($document->mime, ['application/pdf', 'image/jpeg', 'image/png', 'image/webp'], true);

        return $disk->download($document->path, $filename, [
            'Content-Type' => $document->mime,
            'X-Content-Type-Options' => 'nosniff',
            'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.addcslashes($filename, '"\\').'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
