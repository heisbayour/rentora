<x-layouts::admin :title="__('staff.title')">
    <x-page-header :title="__('staff.title')" :description="__('staff.description')">
        <x-slot:actions>
            <x-button type="button" icon="plus" x-data x-on:click="$dispatch('open-modal', 'invite-staff')">{{ __('staff.invite') }}</x-button>
        </x-slot:actions>
    </x-page-header>

    @if (old('_form') !== 'invite' && ($errors->has('member') || $errors->has('role')))
        <x-alert type="danger" class="mb-4">{{ $errors->first('member') ?: $errors->first('role') }}</x-alert>
    @endif

    <x-invite-share />

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2" data-no-guard>
        <div class="w-full sm:w-72">
            <x-input name="q" :value="$search" :placeholder="__('staff.search')" aria-label="{{ __('staff.search') }}" />
        </div>
        <a href="{{ route('app.staff.index', ['q' => $search ?: null]) }}" @class(['rounded-full px-3 py-1.5 text-sm font-medium', 'bg-primary-50 text-primary-700' => ! $role, 'bg-surface text-ink-muted ring-1 ring-border hover:text-ink' => $role])>{{ __('staff.all_roles') }}</a>
        @foreach ($roles as $option)
            <a href="{{ route('app.staff.index', ['q' => $search ?: null, 'role' => $option->value]) }}"
                @class(['rounded-full px-3 py-1.5 text-sm font-medium', 'bg-primary-50 text-primary-700' => $role === $option->value, 'bg-surface text-ink-muted ring-1 ring-border hover:text-ink' => $role !== $option->value])>{{ $option->label() }}</a>
        @endforeach
    </form>

    @if ($staff->isEmpty())
        <x-empty-state icon="user-group" :title="__('staff.empty_title')" :text="__('staff.empty_text')" />
    @else
        <x-table :caption="__('staff.title')">
            <x-slot:head>
                <th>{{ __('staff.name') }}</th>
                <th>{{ __('staff.role') }}</th>
                <th>{{ __('staff.status') }}</th>
                <th>{{ __('staff.last_login') }}</th>
                <th><span class="sr-only">{{ __('staff.actions') }}</span></th>
            </x-slot:head>
            @foreach ($staff as $member)
                @php($locked = $member->isDeveloper() && ! $actor->isDeveloper())
                <tr>
                    <td data-label="{{ __('staff.name') }}">
                        <div class="flex items-center gap-3 text-left">
                            <x-avatar :name="$member->name" size="sm" />
                            <div>
                                <p class="font-medium text-ink">{{ $member->name }}</p>
                                <p class="text-xs text-ink-muted">{{ $member->email }}</p>
                            </div>
                        </div>
                    </td>
                    <td data-label="{{ __('staff.role') }}"><x-status-chip :status="$member->role" /></td>
                    <td data-label="{{ __('staff.status') }}">
                        @if ($member->awaitingPassword())
                            <x-status-chip :label="__('staff.awaiting_password')" color="warning" />
                        @else
                            <x-status-chip :label="$member->is_active ? __('staff.active') : __('staff.inactive')" :color="$member->is_active ? 'success' : 'neutral'" />
                        @endif
                    </td>
                    <td data-label="{{ __('staff.last_login') }}" class="text-ink-muted">
                        {{ $member->last_login_at?->timezone($timezone)->format('j M Y, H:i') ?? __('staff.never') }}
                    </td>
                    <td data-label="" class="text-right">
                        @unless ($locked)
                            <div class="flex flex-wrap justify-end gap-2">
                                @if ($member->awaitingPassword())
                                    <form method="POST" action="{{ route('app.staff.invite-link', $member) }}">
                                        @csrf
                                        <x-button size="sm" variant="secondary" icon="link">{{ __('staff.get_invite_link') }}</x-button>
                                    </form>
                                @endif
                                <x-button type="button" size="sm" variant="ghost" x-data x-on:click="$dispatch('open-modal', 'edit-staff-{{ $member->id }}')">{{ __('staff.edit') }}</x-button>
                            </div>
                        @endunless
                    </td>
                </tr>
            @endforeach
        </x-table>
        <x-pagination :paginator="$staff" class="mt-4" />
    @endif

    <x-modal name="invite-staff" :title="__('staff.invite_title')" :show="$errors->any() && old('_form') === 'invite'">
        <p class="mb-4 text-ink-muted">{{ __('staff.invite_intro') }}</p>
        <form method="POST" action="{{ route('app.staff.store') }}" class="space-y-4">
            @csrf
            <input type="hidden" name="_form" value="invite">
            <x-input name="name" :label="__('staff.name')" required />
            <x-input name="email" type="email" :label="__('staff.email')" required />
            <x-input name="phone" type="tel" :label="__('staff.phone')" optional />
            <x-select name="role" :label="__('staff.role')" :options="collect($assignable)->mapWithKeys(fn ($r) => [$r->value => $r->label().': '.__('staff.role_help.'.$r->value)])->all()" value="manager" />
            <div class="flex justify-end gap-2 pt-2">
                <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'invite-staff')">{{ __('ui.cancel') }}</x-button>
                <x-button>{{ __('staff.send_invite') }}</x-button>
            </div>
        </form>
    </x-modal>

    @foreach ($staff as $member)
        @continue($member->isDeveloper() && ! $actor->isDeveloper())
        <x-modal name="edit-staff-{{ $member->id }}" :title="__('staff.edit_title', ['name' => $member->name])">
            <form method="POST" action="{{ route('app.staff.update', $member) }}" class="space-y-4">
                @csrf
                @method('PUT')
                <x-select name="role" :id="'role-'.$member->id" :label="__('staff.role')" :options="collect($assignable)->mapWithKeys(fn ($r) => [$r->value => $r->label()])->all()" :value="$member->role" />
                <x-toggle name="is_active" :id="'active-'.$member->id" :label="__('staff.account_active')" :hint="__('staff.account_active_hint')" :checked="$member->is_active" />
                <div class="flex justify-end gap-2 pt-2">
                    <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', 'edit-staff-{{ $member->id }}')">{{ __('ui.cancel') }}</x-button>
                    <x-button>{{ __('ui.save') }}</x-button>
                </div>
            </form>
            @unless ($member->is($actor))
                <div class="mt-6 flex flex-wrap gap-2 border-t border-border pt-4">
                    @if ($member->awaitingPassword())
                        <form method="POST" action="{{ route('app.staff.resend', $member) }}">@csrf<x-button size="sm" variant="secondary" icon="paper-airplane">{{ __('staff.resend') }}</x-button></form>
                        <form method="POST" action="{{ route('app.staff.invite-link', $member) }}">@csrf<x-button size="sm" variant="secondary" icon="link">{{ __('staff.get_invite_link') }}</x-button></form>
                    @elseif ($member->is_active)
                        <form method="POST" action="{{ route('app.staff.reset-link', $member) }}">@csrf<x-button size="sm" variant="secondary" icon="key">{{ __('staff.get_reset_link') }}</x-button></form>
                    @endif
                    <x-button type="button" size="sm" variant="danger" icon="trash" x-on:click="$dispatch('close-modal', 'edit-staff-{{ $member->id }}'); $dispatch('open-modal', 'remove-staff-{{ $member->id }}')">{{ __('staff.remove') }}</x-button>
                </div>
            @endunless
        </x-modal>
        @unless ($member->is($actor))
            <x-confirm name="remove-staff-{{ $member->id }}" :action="route('app.staff.destroy', $member)"
                :title="__('staff.remove_title', ['name' => $member->name])" :consequences="__('staff.remove_text', ['name' => $member->name])"
                :confirm-label="__('staff.remove')" />
        @endunless
    @endforeach
</x-layouts::admin>
