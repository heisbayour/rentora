<x-layouts::auth :title="__('auth.reset.title')" :heading="__('auth.reset.heading')">
    <form method="POST" action="{{ route('password.update') }}" class="space-y-5">
        @csrf
        <input type="hidden" name="token" value="{{ $request->route('token') }}">
        <x-input name="email" type="email" :label="__('auth.login.email')" :value="$request->query('email')" autocomplete="username" required />
        <x-input name="password" type="password" :label="__('auth.reset.password')" autocomplete="new-password" required autofocus />
        <x-input name="password_confirmation" type="password" :label="__('auth.reset.confirm')" autocomplete="new-password" required />
        <x-button class="w-full">{{ __('auth.reset.submit') }}</x-button>
    </form>
</x-layouts::auth>
