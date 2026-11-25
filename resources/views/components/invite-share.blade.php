{{-- Shown after an invite is sent or resent: the signed link with Copy and WhatsApp share, so invites work before SMTP or SMS is configured. Data is flashed by the controller as session('invite_share'). --}}
@php
    $share = session('invite_share');
@endphp

@if (is_array($share) && ($share['link'] ?? '') !== '')
    <x-card {{ $attributes->class('mb-4') }} x-data="{ copied: false, copy() { const el = this.$refs.link; el.select(); const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2000); }; if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(el.value).then(done); } else { document.execCommand('copy'); done(); } } }">
        <p class="font-medium text-ink">{{ __('ui.invite_share.title', ['name' => $share['name']]) }}</p>
        <p class="mt-1 text-sm text-ink-muted">{{ __('ui.invite_share.text', ['days' => config('rentora.links.invite_days')]) }}</p>
        <label for="invite-share-link" class="sr-only">{{ __('ui.invite_share.label') }}</label>
        <input id="invite-share-link" x-ref="link" type="text" readonly value="{{ $share['link'] }}"
            class="mt-3 w-full rounded-control border-border bg-canvas text-sm text-ink focus:border-primary-600 focus:ring-2 focus:ring-primary-600" x-on:focus="$el.select()">
        <div class="mt-3 flex flex-wrap gap-2">
            <x-button type="button" variant="secondary" icon="clipboard-document" x-on:click="copy()">
                <span x-show="! copied">{{ __('ui.invite_share.copy') }}</span>
                <span x-show="copied" x-cloak aria-live="polite">{{ __('ui.invite_share.copied') }}</span>
            </x-button>
            <x-button variant="secondary" :href="$share['whatsapp']" target="_blank" rel="noopener" icon="chat-bubble-left-ellipsis">{{ __('ui.invite_share.whatsapp') }}</x-button>
        </div>
    </x-card>
@endif
