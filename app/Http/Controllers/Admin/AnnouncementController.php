<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Messaging\PublishAnnouncement;
use App\Enums\AnnouncementAudience;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AnnouncementRequest;
use App\Models\Announcement;
use App\Models\Property;
use App\Models\Tenant;
use App\Services\Messaging\AnnouncementAudienceResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Announcements to tenants (spec 8.8). Owners and managers. */
class AnnouncementController extends Controller
{
    public function index(): View
    {
        return view('admin.announcements.index', [
            'announcements' => Announcement::query()->with('author')->latest('id')->paginate((int) config('rentora.per_page')),
        ]);
    }

    public function create(): View
    {
        return view('admin.announcements.create', [
            'audiences' => AnnouncementAudience::options(),
            'properties' => Property::query()->orderBy('name')->pluck('name', 'id'),
            'tenants' => Tenant::query()->orderBy('full_name')->pluck('full_name', 'id'),
        ]);
    }

    public function store(AnnouncementRequest $request, PublishAnnouncement $publish): RedirectResponse
    {
        $announcement = $publish->handle(
            $request->user(),
            (string) $request->validated('title'),
            (string) $request->validated('body'),
            $request->audience(),
            $request->audienceIds(),
            array_values((array) $request->validated('channels')),
        );

        return redirect()->route('app.announcements.index')
            ->with('toast', ['message' => __('announcements.sent', ['count' => $announcement->recipient_count]), 'type' => 'success']);
    }

    /** Live recipient count for the compose form. */
    public function recipients(Request $request, AnnouncementAudienceResolver $resolver): JsonResponse
    {
        $data = $request->validate([
            'audience' => ['required', Rule::in(AnnouncementAudience::values())],
            'ids' => ['array'],
            'ids.*' => ['integer'],
        ]);

        return response()->json(['count' => $resolver->count(
            AnnouncementAudience::from($data['audience']),
            array_values(array_map('intval', $data['ids'] ?? [])),
        )]);
    }
}
