<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\InquiryStatus;
use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Inbox for public website inquiries (spec 8.11). Owners and managers. */
class InquiryController extends Controller
{
    public function index(Request $request): View
    {
        $status = (string) $request->query('status', '');

        return view('admin.inquiries.index', [
            'inquiries' => Inquiry::query()->with('unit.property')
                ->when(in_array($status, InquiryStatus::values(), true), fn (Builder $q) => $q->where('status', $status))
                ->latest('id')
                ->paginate((int) config('rentora.per_page'))
                ->withQueryString(),
            'status' => $status,
            'statuses' => InquiryStatus::options(),
        ]);
    }

    public function update(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(InquiryStatus::values())]]);
        $inquiry->forceFill(['status' => $data['status']])->save();

        return back()->with('toast', __('inquiries.updated'));
    }
}
