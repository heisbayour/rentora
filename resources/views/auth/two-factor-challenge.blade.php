<x-layouts::auth :title="__('auth.two_factor.title')" :heading="__('auth.two_factor.heading')">
    <div x-data="{ recovery: @js($errors->has('recovery_code')) }">
        <p class="mb-5 text-sm text-ink-muted" x-show="!recovery">{{ __('auth.two_factor.intro') }}</p>
        <p class="mb-5 text-sm text-ink-muted" x-show="recovery" x-cloak>{{ __('auth.two_factor.recovery_intro') }}</p>
        <form method="POST" action="{{ route('two-factor.login') }}" class="space-y-5">
            @csrf
            <div x-show="!recovery">
                <x-input name="code" :label="__('auth.two_factor.code')" inputmode="numeric" autocomplete="one-time-code" autofocus x-bind:disabled="recovery" />
            </div>
            <div x-show="recovery" x-cloak>
                <x-input name="recovery_code" :label="__('auth.two_factor.recovery_code')" autocomplete="one-time-code" x-bind:disabled="!recovery" />
            </div>
            <x-button class="w-full">{{ __('auth.two_factor.submit') }}</x-button>
        </form>
        <button type="button" class="mt-4 w-full text-center text-sm font-medium text-primary-600 hover:underline" @click="recovery = !recovery">
            <span x-show="!recovery">{{ __('auth.two_factor.use_recovery') }}</span>
            <span x-show="recovery" x-cloak>{{ __('auth.two_factor.use_code') }}</span>
        </button>
    </div>
</x-layouts::auth>
