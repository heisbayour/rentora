<x-layouts::auth :title="__('auth.confirm.title')" :heading="__('auth.confirm.heading')">
    <p class="mb-5 text-sm text-ink-muted">{{ __('auth.confirm.intro') }}</p>
    <form method="POST" action="{{ route('password.confirm') }}" class="space-y-5">
        @csrf
        <x-input name="password" type="password" :label="__('auth.login.password')" autocomplete="current-password" required autofocus />
        <x-button class="w-full">{{ __('auth.confirm.submit') }}</x-button>
    </form>
</x-layouts::auth>
