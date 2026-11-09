<x-layouts::auth :title="__('tenants.invitation.title')" :heading="__('tenants.invitation.heading', ['name' => $tenant->full_name])">
    <p class="mb-5 text-sm text-ink-muted">{{ __('tenants.invitation.intro') }}</p>
    <form method="POST" action="{{ $action }}" class="space-y-5">
        @csrf
        @if ($tenant->email)
            <x-input name="email_display" type="email" :label="__('tenants.invitation.email')" :value="$tenant->email" disabled />
        @else
            <x-input name="email" type="email" :label="__('tenants.invitation.email')" autocomplete="username" required />
        @endif
        <x-input name="password" type="password" :label="__('tenants.invitation.password')" :hint="__('tenants.invitation.password_hint')" autocomplete="new-password" required autofocus />
        <x-input name="password_confirmation" type="password" :label="__('tenants.invitation.confirm')" autocomplete="new-password" required />
        <x-button class="w-full">{{ __('tenants.invitation.submit') }}</x-button>
    </form>
</x-layouts::auth>
