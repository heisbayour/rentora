{{-- Shown after an invite or password reset link is issued: the link with Copy, WhatsApp, SMS, Email and the phone's share sheet, plus whether email or SMS was also sent (DECISIONS 104). Data is flashed by the controller as session('invite_share') through App\Support\InviteShare. --}}
@php
    $share = session('invite_share');
@endphp

@if (is_array($share) && ($share['link'] ?? '') !== '')
    <x-card {{ $attributes->class('mb-4') }} data-share-panel
        x-data="{ copied: false, canShare: typeof navigator !== 'undefined' && typeof navigator.share === 'function',
            copy() { const el = this.$refs.link; el.select(); const done = () => { this.copied = true; setTimeout(() => this.copied = false, 2000); }; if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(el.value).then(done, () => { document.execCommand('copy'); done(); }); } else { document.execCommand('copy'); done(); } },
            share() { navigator.share({ title: this.$root.dataset.subject, text: this.$root.dataset.message }).catch(() => {}); } }"
        data-subject="{{ $share['subject'] ?? '' }}" data-message="{{ $share['message'] ?? $share['link'] }}">
        <p class="font-medium text-ink">{{ $share['title'] ?? __('ui.invite_share.title', ['name' => $share['name']]) }}</p>
        <p class="mt-1 text-sm text-ink-muted">{{ $share['text'] ?? '' }}</p>

        <ul class="mt-3 space-y-1 text-sm" aria-live="polite">
            @forelse ($share['deliveries'] ?? [] as $delivery)
                <li @class(['flex items-start gap-2', 'text-success' => $delivery['ok'], 'text-warning' => ! $delivery['ok']])>
                    <x-dynamic-component :component="'heroicon-m-'.($delivery['ok'] ? 'check-circle' : 'exclamation-triangle')" class="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
                    <span>{{ $delivery['message'] }}</span>
                </li>
            @empty
                <li class="text-ink-muted">{{ __('ui.invite_share.not_sent') }}</li>
            @endforelse
        </ul>

        <label for="invite-share-link" class="sr-only">{{ __('ui.invite_share.label') }}</label>
        <input id="invite-share-link" x-ref="link" type="text" readonly value="{{ $share['link'] }}"
            class="mt-3 w-full rounded-control border-border bg-canvas text-sm text-ink focus:border-primary-600 focus:ring-2 focus:ring-primary-600" x-on:focus="$el.select()">
        <div class="mt-3 flex flex-wrap gap-2">
            <x-button type="button" icon="clipboard-document" x-on:click="copy()">
                <span x-show="! copied">{{ __('ui.invite_share.copy') }}</span>
                <span x-show="copied" x-cloak>{{ __('ui.invite_share.copied') }}</span>
            </x-button>
            <x-button variant="secondary" :href="$share['whatsapp']" target="_blank" rel="noopener" icon="chat-bubble-left-ellipsis">{{ __('ui.invite_share.whatsapp') }}</x-button>
            @isset($share['sms'])
                <x-button variant="secondary" :href="$share['sms']" icon="device-phone-mobile">{{ __('ui.invite_share.sms') }}</x-button>
            @endisset
            @isset($share['mailto'])
                <x-button variant="secondary" :href="$share['mailto']" icon="envelope">{{ __('ui.invite_share.email') }}</x-button>
            @endisset
            <x-button type="button" variant="secondary" icon="share" x-show="canShare" x-cloak x-on:click="share()">{{ __('ui.invite_share.share') }}</x-button>
        </div>
    </x-card>
@endif
