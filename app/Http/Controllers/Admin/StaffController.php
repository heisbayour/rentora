<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Staff\InviteStaff;
use App\Actions\Staff\UpdateStaffMember;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\User;
use App\Support\CurrentOrganization;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-staff');

        $search = trim((string) $request->query('q', ''));
        $role = $request->query('role');
        $staff = User::query()->staff()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when(in_array($role, ['owner', 'manager', 'caretaker'], true), fn ($query) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(config('rentora.per_page'))
            ->withQueryString();

        return view('admin.staff.index', [
            'staff' => $staff,
            'search' => $search,
            'role' => $role,
            'roles' => Role::staff(),
            'timezone' => app(CurrentOrganization::class)->get()->timezone ?? config('rentora.defaults.timezone'),
        ]);
    }

    public function store(InviteStaffRequest $request, InviteStaff $invite): RedirectResponse
    {
        $member = $invite->handle($request->validated());

        return redirect()->route('app.staff.index')->with('toast', __('staff.invited', ['name' => $member->name]));
    }

    public function update(UpdateStaffRequest $request, User $member, UpdateStaffMember $update): RedirectResponse
    {
        abort_unless($member->isStaff(), 404);

        $update->handle($request->user(), $member, Role::from($request->validated('role')), $request->boolean('is_active'));

        return redirect()->route('app.staff.index')->with('toast', __('staff.updated', ['name' => $member->name]));
    }

    public function resend(User $member, InviteStaff $invite): RedirectResponse
    {
        $this->authorize('manage-staff');
        abort_unless($member->isStaff(), 404);

        $invite->send($member);

        return redirect()->route('app.staff.index')->with('toast', __('staff.resent', ['name' => $member->name]));
    }
}
