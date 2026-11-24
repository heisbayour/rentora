{{-- "Install app" card: Android uses the browser's install prompt, iOS Safari gets instructions (spec 8.14). --}}
<div x-data x-cloak x-show="$store.install.canPrompt || $store.install.showIos"
    {{ $attributes->class('flex items-start gap-3 rounded-card border border-border bg-surface p-4 shadow-card') }}>
    <x-heroicon-o-device-phone-mobile class="h-6 w-6 shrink-0 text-primary-600" aria-hidden="true" />
    <div class="min-w-0 flex-1">
        <p class="text-sm font-semibold text-ink">{{ __('portal.install.title') }}</p>
        <p class="text-sm text-ink-muted" x-show="$store.install.canPrompt">{{ __('portal.install.text', ['app' => app(\App\Support\CurrentOrganization::class)->get()->name ?? config('rentora.name')]) }}</p>
        <p class="text-sm text-ink-muted" x-show="!$store.install.canPrompt && $store.install.showIos">{{ __('portal.install.ios') }}</p>
        <div class="mt-3 flex gap-2">
            <x-button size="sm" type="button" x-show="$store.install.canPrompt" x-on:click="$store.install.prompt()">{{ __('portal.install.button') }}</x-button>
            <x-button size="sm" type="button" variant="ghost" x-on:click="$store.install.dismiss()">{{ __('portal.install.dismiss') }}</x-button>
        </div>
    </div>
</div>
