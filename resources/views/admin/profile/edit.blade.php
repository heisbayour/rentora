<x-layouts::admin :title="__('profile.title')">
    <x-page-header :title="__('profile.title')" />

    @php
        $status = session('status');
        $statusText = $status ? __('profile.status.'.$status) : null;
    @endphp
    @if ($statusText && $statusText !== 'profile.status.'.$status)
        <x-alert type="success" class="mb-6">{{ $statusText }}</x-alert>
    @endif

    <div class="grid max-w-3xl gap-6">
        <x-card :title="__('profile.details')">
            <form method="POST" action="{{ route('app.profile.update') }}" class="grid gap-5 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-input name="name" :label="__('profile.name')" :value="$user->name" required autocomplete="name" />
                <x-input name="email_display" type="email" :label="__('profile.email')" :value="$user->email" :hint="__('profile.email_hint')" disabled />
                <x-input name="phone" type="tel" :label="__('profile.phone')" :value="$user->phone" optional autocomplete="tel" />
                <div class="sm:col-span-2">
                    <x-button>{{ __('profile.save') }}</x-button>
                </div>
            </form>
        </x-card>

        <x-card :title="__('profile.password')">
            <form method="POST" action="{{ route('user-password.update') }}" class="grid gap-5 sm:grid-cols-2">
                @csrf
                @method('PUT')
                <x-input name="current_password" type="password" bag="updatePassword" :label="__('profile.current_password')" autocomplete="current-password" required class="sm:col-span-2" />
                <x-input name="password" type="password" bag="updatePassword" :label="__('profile.new_password')" :hint="__('auth.invitation.staff_hint')" autocomplete="new-password" required />
                <x-input name="password_confirmation" type="password" bag="updatePassword" :label="__('profile.confirm_password')" autocomplete="new-password" required />
                <div class="sm:col-span-2">
                    <x-button>{{ __('profile.update_password') }}</x-button>
                </div>
            </form>
        </x-card>

        @if ($user->canUseTwoFactor())
            <x-card :title="__('profile.two_factor')" :description="__('profile.two_factor_intro')">
                @if (! $user->two_factor_secret)
                    <x-status-chip :label="__('profile.two_factor_disabled')" color="neutral" />
                    <form method="POST" action="{{ route('two-factor.enable') }}" class="mt-4">
                        @csrf
                        <x-button>{{ __('profile.two_factor_enable') }}</x-button>
                    </form>
                @elseif (! $user->two_factor_confirmed_at)
                    <p class="text-sm text-ink-muted">{{ __('profile.two_factor_scan') }}</p>
                    <div class="mt-4 inline-block rounded-control border border-border bg-white p-3">{!! $user->twoFactorQrCodeSvg() !!}</div>
                    <p class="mt-3 text-xs text-ink-muted">{{ __('profile.two_factor_setup_key') }}: <span class="font-mono text-ink">{{ decrypt($user->two_factor_secret) }}</span></p>
                    <form method="POST" action="{{ route('two-factor.confirm') }}" class="mt-4 flex flex-wrap items-end gap-3">
                        @csrf
                        <x-input name="code" bag="confirmTwoFactorAuthentication" :label="__('profile.two_factor_code')" inputmode="numeric" autocomplete="one-time-code" required class="w-48" />
                        <x-button>{{ __('profile.two_factor_confirm') }}</x-button>
                    </form>
                @else
                    <x-status-chip :label="__('profile.two_factor_enabled')" color="success" />
                    @if (in_array(session('status'), ['two-factor-authentication-confirmed', 'recovery-codes-generated'], true))
                        <div class="mt-4">
                            <p class="text-sm font-medium text-ink">{{ __('profile.recovery_codes') }}</p>
                            <p class="text-sm text-ink-muted">{{ __('profile.recovery_codes_intro') }}</p>
                            <ul class="mt-3 grid grid-cols-2 gap-2 rounded-control bg-canvas p-4 font-mono text-sm">
                                @foreach ($user->recoveryCodes() as $code)
                                    <li>{{ $code }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif
                    <div class="mt-4 flex flex-wrap gap-3">
                        <form method="POST" action="{{ route('two-factor.regenerate-recovery-codes') }}">
                            @csrf
                            <x-button variant="secondary">{{ __('profile.regenerate_codes') }}</x-button>
                        </form>
                        <form method="POST" action="{{ route('two-factor.disable') }}">
                            @csrf
                            @method('DELETE')
                            <x-button variant="danger">{{ __('profile.two_factor_disable') }}</x-button>
                        </form>
                    </div>
                @endif
            </x-card>
        @endif
    </div>
</x-layouts::admin>
