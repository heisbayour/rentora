@php
    $links = collect($tabs)->mapWithKeys(fn ($label, $key) => [$key => route('app.settings.edit', ['tab' => $key])])->all();
    $v = fn (string $key) => $values[$key] ?? null;
@endphp
<x-layouts::admin :title="__('settings.title')">
    <x-page-header :title="__('settings.title')" :description="__('settings.description')" />

    <x-tabs :tabs="$tabs" :links="$links" :active="$tab" class="mb-6" />

    <div class="grid gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            @if ($tab === 'organization')
                <form method="POST" action="{{ route('app.settings.update', 'organization') }}" enctype="multipart/form-data">
                    @csrf @method('PUT')
                    <x-card :title="__('settings.tabs.organization')">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input name="name" :label="__('onboarding.organization.name')" :value="$organization->name" required class="sm:col-span-2" />
                            <x-input name="email" type="email" :label="__('onboarding.organization.email')" :value="$organization->email" optional />
                            <x-input name="phone" type="tel" :label="__('onboarding.organization.phone')" :value="$organization->phone" optional />
                            <x-textarea name="address" :label="__('onboarding.organization.address')" :value="$organization->address" rows="2" optional class="sm:col-span-2" />
                            <x-select name="timezone" :label="__('onboarding.organization.timezone')" :options="$timezones" :value="$organization->timezone" />
                            <x-select name="currency" :label="__('onboarding.organization.currency')" :options="$currencies" :value="$organization->currency" />
                            <x-input name="primary_color" type="color" :label="__('onboarding.organization.primary_color')" :value="$organization->primary_color" class="[&_input]:p-1" />
                            <x-file-dropzone name="logo" :label="__('onboarding.organization.logo')" accept="image/jpeg,image/png,image/webp" class="sm:col-span-2" />
                        </div>
                        <x-slot:footer><div class="flex justify-end"><x-button>{{ __('settings.save') }}</x-button></div></x-slot:footer>
                    </x-card>
                </form>
            @elseif ($tab === 'website')
                <form method="POST" action="{{ route('app.settings.update', 'website') }}">
                    @csrf @method('PUT')
                    <x-card :title="__('settings.tabs.website')" :description="__('settings.website.intro')">
                        <div class="space-y-5">
                            <x-toggle name="enabled" :label="__('settings.website.enabled')" :hint="__('settings.website.enabled_hint')" :checked="$websiteEnabled" />
                            <x-input name="website_headline" :label="__('settings.website.headline')" :value="$organization->website_headline" :placeholder="__('public.default_headline')" optional />
                            <x-textarea name="website_about" :label="__('settings.website.about')" :value="$organization->website_about" rows="5" optional />
                            <p class="text-sm"><a href="{{ url('/') }}" target="_blank" rel="noopener" class="font-medium text-primary-600 hover:underline">{{ __('settings.website.view') }}</a></p>
                        </div>
                        <x-slot:footer><div class="flex justify-end"><x-button>{{ __('settings.save') }}</x-button></div></x-slot:footer>
                    </x-card>
                </form>
            @elseif ($tab === 'billing')
                <form method="POST" action="{{ route('app.settings.update', 'billing') }}">
                    @csrf @method('PUT')
                    <x-card :title="__('settings.tabs.billing')" :description="__('settings.billing.intro')">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input name="billing__default_lead_days" type="number" min="0" max="120" :label="__('settings.billing.lead_days')" :value="$v('billing.default_lead_days')" :hint="__('settings.billing.lead_days_hint')" optional />
                            <x-input name="billing__default_grace_days" type="number" min="0" max="90" :label="__('settings.billing.grace_days')" :value="$v('billing.default_grace_days')" placeholder="5" optional />
                            <x-select name="billing__late_fee_type" :label="__('settings.billing.late_fee_type')" :options="$lateFeeTypes" :value="$v('billing.late_fee_type') ?? 'none'" />
                            <x-input name="billing__late_fee_value" type="number" min="0" step="0.01" :label="__('settings.billing.late_fee_value')" :value="$v('billing.late_fee_value') !== null ? (int) $v('billing.late_fee_value') / 100 : null" :hint="__('settings.billing.late_fee_value_hint', ['symbol' => $currencySymbol])" optional />
                            <div class="sm:col-span-2">
                                <x-toggle name="billing__allow_overpayment" :label="__('settings.billing.allow_overpayment')" :hint="__('settings.billing.allow_overpayment_hint')" :checked="$v('billing.allow_overpayment') === '1'" />
                            </div>
                            <h3 class="text-sm font-semibold text-ink sm:col-span-2">{{ __('settings.billing.bank_heading') }}</h3>
                            <x-input name="billing__bank_name" :label="__('portal.pay.bank_name')" :value="$v('billing.bank_name')" optional />
                            <x-input name="billing__bank_account_number" :label="__('portal.pay.account_number')" :value="$v('billing.bank_account_number')" inputmode="numeric" optional />
                            <x-input name="billing__bank_account_name" :label="__('portal.pay.account_name')" :value="$v('billing.bank_account_name')" optional class="sm:col-span-2" />
                        </div>
                        <x-slot:footer><div class="flex justify-end"><x-button>{{ __('settings.save') }}</x-button></div></x-slot:footer>
                    </x-card>
                </form>
            @elseif ($tab === 'payments')
                <form method="POST" action="{{ route('app.settings.update', 'payments') }}">
                    @csrf @method('PUT')
                    <x-card :title="__('settings.tabs.payments')" :description="__('settings.payments.intro', ['callback' => route('pay.callback')])">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-select name="paystack__mode" :label="__('settings.payments.mode')" :options="['test' => __('settings.payments.test'), 'live' => __('settings.payments.live')]" :value="$v('paystack.mode') ?? 'test'" />
                            <div></div>
                            <x-input name="paystack__public_key" :label="__('settings.payments.public_key')" :value="$v('paystack.public_key')" placeholder="pk_test_..." optional class="sm:col-span-2" />
                            <x-input name="paystack__secret_key" type="password" autocomplete="off" :label="__('settings.payments.secret_key')" :placeholder="$v('paystack.secret_key') ?? 'sk_test_...'" :hint="__('settings.secret_hint')" optional class="sm:col-span-2" />
                        </div>
                        <x-slot:footer><div class="flex justify-end"><x-button>{{ __('settings.save') }}</x-button></div></x-slot:footer>
                    </x-card>
                </form>
                <form method="POST" action="{{ route('app.settings.test', 'paystack') }}" class="mt-4">
                    @csrf
                    <x-button variant="secondary" icon="signal">{{ __('settings.test.paystack') }}</x-button>
                </form>
            @elseif ($tab === 'email')
                <form method="POST" action="{{ route('app.settings.update', 'email') }}">
                    @csrf @method('PUT')
                    <x-card :title="__('settings.tabs.email')" :description="__('settings.email.intro')">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-input name="mail__host" :label="__('settings.email.host')" :value="$v('mail.host')" placeholder="smtp-relay.brevo.com" optional />
                            <x-input name="mail__port" type="number" :label="__('settings.email.port')" :value="$v('mail.port')" placeholder="587" optional />
                            <x-select name="mail__encryption" :label="__('settings.email.encryption')" :options="['tls' => 'TLS (587)', 'ssl' => 'SSL (465)', 'none' => __('settings.email.none')]" :value="$v('mail.encryption') ?? 'tls'" />
                            <x-input name="mail__username" :label="__('settings.email.username')" :value="$v('mail.username')" autocomplete="off" optional />
                            <x-input name="mail__password" type="password" autocomplete="off" :label="__('settings.email.password')" :placeholder="$v('mail.password')" :hint="__('settings.secret_hint')" optional class="sm:col-span-2" />
                            <x-input name="mail__from_address" type="email" :label="__('settings.email.from_address')" :value="$v('mail.from_address')" optional />
                            <x-input name="mail__from_name" :label="__('settings.email.from_name')" :value="$v('mail.from_name')" :placeholder="$organization->name" optional />
                        </div>
                        <x-slot:footer><div class="flex justify-end"><x-button>{{ __('settings.save') }}</x-button></div></x-slot:footer>
                    </x-card>
                </form>
                <form method="POST" action="{{ route('app.settings.test', 'email') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <x-input name="to" type="email" :label="__('settings.test.send_to')" :value="auth()->user()->email" required class="w-full sm:w-72" />
                    <x-button variant="secondary" icon="envelope">{{ __('settings.test.email') }}</x-button>
                </form>
            @elseif ($tab === 'sms')
                <form method="POST" action="{{ route('app.settings.update', 'sms') }}">
                    @csrf @method('PUT')
                    <x-card :title="__('settings.tabs.sms')" :description="__('settings.sms.intro')">
                        <div class="grid gap-5 sm:grid-cols-2">
                            <x-select name="sms__driver" :label="__('settings.sms.driver')" :options="$smsDrivers" :value="$v('sms.driver') ?? config('rentora.sms.driver')" />
                            <x-select name="sms__termii_channel" :label="__('settings.sms.channel')" :options="['generic' => 'generic', 'dnd' => 'dnd']" :value="$v('sms.termii_channel') ?? 'generic'" :hint="__('settings.sms.channel_hint')" />
                            <x-input name="sms__termii_api_key" type="password" autocomplete="off" :label="__('settings.sms.api_key')" :placeholder="$v('sms.termii_api_key')" :hint="__('settings.secret_hint')" optional />
                            <x-input name="sms__termii_sender_id" maxlength="11" :label="__('settings.sms.sender_id')" :value="$v('sms.termii_sender_id')" optional />
                            <x-input name="sms__termii_base_url" type="url" :label="__('settings.sms.base_url')" :value="$v('sms.termii_base_url')" :placeholder="config('rentora.sms.termii.base_url')" :hint="__('settings.sms.base_url_hint')" optional class="sm:col-span-2" />
                        </div>
                        <x-slot:footer><div class="flex justify-end"><x-button>{{ __('settings.save') }}</x-button></div></x-slot:footer>
                    </x-card>
                </form>
                <form method="POST" action="{{ route('app.settings.test', 'sms') }}" class="mt-4 flex flex-wrap items-end gap-3">
                    @csrf
                    <x-input name="to" type="tel" :label="__('settings.test.send_to')" :value="auth()->user()->phone" required class="w-full sm:w-72" />
                    <x-button variant="secondary" icon="chat-bubble-left">{{ __('settings.test.sms') }}</x-button>
                </form>
            @endif
        </div>

        <aside>
            <x-card :title="__('settings.version.title')">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('settings.version.version') }}</dt><dd class="font-medium tabular-nums">{{ $version['version'] }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('settings.version.commit') }}</dt><dd class="font-mono text-xs">{{ $version['commit'] ?? '-' }}</dd></div>
                    <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('settings.version.built_at') }}</dt><dd class="text-xs">{{ $version['built_at'] ?? '-' }}</dd></div>
                </dl>
                <p class="mt-4 text-sm"><a href="{{ route('app.health') }}" class="font-medium text-primary-600 hover:underline">{{ __('settings.version.health') }}</a></p>
            </x-card>
        </aside>
    </div>
</x-layouts::admin>
