<?php

declare(strict_types=1);

namespace App\Actions\Documents;

use App\Enums\DocumentVisibility;
use App\Models\Contracts\Documentable;
use App\Models\Document;
use App\Services\ActivityLogger;
use App\Support\PrivateFiles;
use Illuminate\Http\UploadedFile;

/** The shared documents module: any model using HasDocuments can carry files. */
class ManageDocuments
{
    public function __construct(
        private readonly PrivateFiles $files,
        private readonly ActivityLogger $logger,
    ) {}

    public function store(Documentable $documentable, UploadedFile $file, ?string $name, DocumentVisibility $visibility, ?int $uploadedBy): Document
    {
        /** @var Document $document */
        $document = $documentable->documents()->create([
            'name' => $name !== null && trim($name) !== '' ? trim($name) : $file->getClientOriginalName(),
            'path' => $this->files->store($file, 'documents/'.$documentable->getMorphClass()),
            'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(),
            'visibility' => $visibility,
            'uploaded_by' => $uploadedBy,
        ]);

        $this->logger->log('document.uploaded', $document, ['name' => $document->name]);

        return $document;
    }

    public function delete(Document $document): void
    {
        $path = $document->path;
        $document->delete();
        $this->files->delete($path);

        $this->logger->log('document.deleted', $document, ['name' => $document->name]);
    }
}
