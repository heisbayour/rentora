<x-layouts::auth :title="__('auth.login.title')" :heading="__('auth.login.heading')">
    <form method="POST" action="{{ route('login') }}" class="space-y-5">
        @csrf
        <x-input name="email" type="email" :label="__('auth.login.email')" autocomplete="username" required autofocus />
        <x-input name="password" type="password" :label="__('auth.login.password')" autocomplete="current-password" required />
        <div class="flex items-center justify-between gap-3">
            <x-checkbox name="remember" :label="__('auth.login.remember')" />
            @if (Route::has('password.request'))
                <a href="{{ route('password.request') }}" class="text-sm font-medium text-primary-600 hover:underline">{{ __('auth.login.forgot') }}</a>
            @endif
        </div>
        <x-button class="w-full">{{ __('auth.login.submit') }}</x-button>
    </form>
</x-layouts::auth>
