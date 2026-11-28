<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Actions\Staff\InviteStaff;
use App\Actions\Staff\IssuePasswordResetLink;
use App\Actions\Staff\RemoveStaffMember;
use App\Actions\Staff\UpdateStaffMember;
use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\InviteStaffRequest;
use App\Http\Requests\Admin\UpdateStaffRequest;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Support\CurrentOrganization;
use App\Support\InviteShare;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class StaffController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorize('manage-staff');

        $search = trim((string) $request->query('q', ''));
        $role = $request->query('role');
        $roleValues = array_map(fn (Role $r) => $r->value, Role::staff());
        $staff = User::query()->staff()
            ->when($search !== '', fn ($query) => $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%")))
            ->when(in_array($role, $roleValues, true), fn ($query) => $query->where('role', $role))
            ->orderBy('name')
            ->paginate(config('rentora.per_page'))
            ->withQueryString();

        $actor = $request->user();
        assert($actor instanceof User);

        return view('admin.staff.index', [
            'staff' => $staff,
            'search' => $search,
            'role' => $role,
            'roles' => Role::staff(),
            'assignable' => Role::assignableBy($actor->role),
            'actor' => $actor,
            'timezone' => app(CurrentOrganization::class)->get()->timezone ?? config('rentora.defaults.timezone'),
        ]);
    }

    public function store(InviteStaffRequest $request, InviteStaff $invite): RedirectResponse
    {
        $result = $invite->handle($request->validated());
        $member = $result['user'];
        InviteShare::flash($member->name, $result['link'], $member->phone, $member->email, [$result['delivery']]);

        $toast = $result['delivery']->ok() ? __('staff.invited', ['name' => $member->name]) : __('staff.invited_share', ['name' => $member->name]);

        return redirect()->route('app.staff.index')->with('toast', $toast);
    }

    public function update(UpdateStaffRequest $request, User $member, UpdateStaffMember $update): RedirectResponse
    {
        abort_unless($member->isStaff(), 404);

        $update->handle($this->actor($request), $member, Role::from($request->validated('role')), $request->boolean('is_active'));

        return redirect()->route('app.staff.index')->with('toast', __('staff.updated', ['name' => $member->name]));
    }

    /** Resend: emails the invitation again when email is set up, and always shows the link. */
    public function resend(Request $request, User $member, InviteStaff $invite): RedirectResponse
    {
        $this->authorize('manage-staff');
        UpdateStaffMember::guard($this->actor($request), $member);

        [$link, $delivery] = $invite->send($member);
        InviteShare::flash($member->name, $link, $member->phone, $member->email, [$delivery]);

        $toast = $delivery->ok() ? __('staff.resent', ['name' => $member->name]) : __('staff.link_ready', ['name' => $member->name]);

        return redirect()->route('app.staff.index')->with('toast', $toast);
    }

    /** "Get invite link": a fresh link for someone who has not set a password yet, nothing sent. */
    public function inviteLink(Request $request, User $member, ActivityLogger $logger): RedirectResponse
    {
        UpdateStaffMember::guard($this->actor($request), $member);
        if (! $member->awaitingPassword()) {
            throw ValidationException::withMessages(['member' => __('staff.already_set_password', ['name' => $member->name])]);
        }

        $logger->log('staff.invite_link_issued', $member);
        InviteShare::flash($member->name, InviteStaff::link($member), $member->phone, $member->email);

        return redirect()->route('app.staff.index')->with('toast', __('staff.link_ready', ['name' => $member->name]));
    }

    /** "Get password reset link" for owners and developers. */
    public function resetLink(Request $request, User $member, IssuePasswordResetLink $issue): RedirectResponse
    {
        UpdateStaffMember::guard($this->actor($request), $member);

        $link = $issue->handle($this->actor($request), $member);
        InviteShare::flash($member->name, $link, $member->phone, $member->email, [], InviteShare::RESET);

        return redirect()->route('app.staff.index')->with('toast', __('staff.link_ready', ['name' => $member->name]));
    }

    public function destroy(Request $request, User $member, RemoveStaffMember $remove): RedirectResponse
    {
        $name = $member->name;
        $mode = $remove->handle($this->actor($request), $member);

        return redirect()->route('app.staff.index')->with('toast', __('staff.removed_'.$mode, ['name' => $name]));
    }

    private function actor(Request $request): User
    {
        $actor = $request->user();
        assert($actor instanceof User);

        return $actor;
    }
}
