<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Documents\ManageDocuments;
use App\Enums\DocumentVisibility;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\DocumentRequest;
use App\Models\Contracts\Documentable;
use App\Models\Document;
use App\Models\Property;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

/** Upload and delete documents on any documentable record (properties now; tenants and leases later). */
class DocumentController extends Controller
{
    /** Morph alias => model class. Add a model here when it gains documents. */
    public const DOCUMENTABLES = [
        'property' => Property::class,
    ];

    public function __construct(private readonly ManageDocuments $documents) {}

    public function store(DocumentRequest $request): RedirectResponse
    {
        $class = self::DOCUMENTABLES[$request->validated('documentable_type')];
        /** @var Documentable&Model $documentable */
        $documentable = $class::query()->findOrFail($request->integer('documentable_id'));
        $this->authorize('update', $documentable);

        $document = $this->documents->store(
            $documentable,
            $request->file('file'),
            $request->validated('name'),
            DocumentVisibility::from($request->validated('visibility')),
            $request->user()->id,
        );

        return back()->with('toast', __('documents.uploaded', ['name' => $document->name]));
    }

    public function destroy(Document $document): RedirectResponse
    {
        $this->authorize('delete', $document);

        $this->documents->delete($document);

        return back()->with('toast', __('documents.deleted', ['name' => $document->name]));
    }
}
