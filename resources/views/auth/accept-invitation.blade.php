<x-layouts::auth :title="__('auth.invitation.title')" :heading="__('auth.invitation.heading', ['name' => $user->name])">
    <p class="mb-5 text-sm text-ink-muted">{{ __('auth.invitation.intro') }}</p>
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        <x-input name="email_display" type="email" :label="__('auth.login.email')" :value="$user->email" disabled />
        <x-input name="password" type="password" :label="__('auth.invitation.password')" autocomplete="new-password"
            :hint="$user->isStaff() ? __('auth.invitation.staff_hint') : null" required autofocus />
        <x-input name="password_confirmation" type="password" :label="__('auth.invitation.confirm')" autocomplete="new-password" required />
        <x-button class="w-full">{{ __('auth.invitation.submit') }}</x-button>
    </form>
</x-layouts::auth>
