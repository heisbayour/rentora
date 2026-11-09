<x-layouts::auth :title="__('auth.forgot.title')" :heading="__('auth.forgot.heading')">
    @if (session('status'))
        <x-alert type="success" class="mb-5">{{ session('status') }}</x-alert>
    @endif
    <p class="mb-5 text-sm text-ink-muted">{{ __('auth.forgot.intro') }}</p>
    <form method="POST" action="{{ route('password.email') }}" class="space-y-5">
        @csrf
        <x-input name="email" type="email" :label="__('auth.login.email')" autocomplete="username" required autofocus />
        <x-button class="w-full">{{ __('auth.forgot.submit') }}</x-button>
    </form>
    <x-slot:below>
        <a href="{{ route('login') }}" class="font-medium text-primary-600 hover:underline">{{ __('auth.forgot.back') }}</a>
    </x-slot:below>
</x-layouts::auth>
