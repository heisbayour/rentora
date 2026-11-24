<?php

declare(strict_types=1);

namespace App\Http\Controllers\Portal;

use App\Enums\DocumentVisibility;
use App\Http\Controllers\Controller;
use App\Models\Document;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Documents shared with the tenant, on their profile or their leases (spec 8.13). */
class DocumentController extends Controller
{
    public function __invoke(Request $request): View
    {
        $tenant = $request->user()->tenant ?? abort(404);
        $leaseIds = $tenant->leases()->pluck('id');

        $documents = Document::query()
            ->where('visibility', DocumentVisibility::Tenant->value)
            ->where(fn (Builder $q) => $q
                ->where(fn (Builder $w) => $w->where('documentable_type', 'tenant')->where('documentable_id', $tenant->id))
                ->orWhere(fn (Builder $w) => $w->where('documentable_type', 'lease')->whereIn('documentable_id', $leaseIds)))
            ->latest('id')
            ->get();

        return view('portal.documents', ['documents' => $documents]);
    }
}
