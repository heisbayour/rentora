<x-layouts::portal :title="__('portal.pay.title')">
    <h1 class="text-xl font-semibold text-ink">{{ __('portal.pay.title') }}</h1>
    <p class="mt-1 text-sm text-ink-muted">{{ __('portal.pay.intro') }}</p>

    @error('amount')<x-alert type="danger" class="mt-4">{{ $message }}</x-alert>@enderror
    @error('payment')<x-alert type="danger" class="mt-4">{{ $message }}</x-alert>@enderror

    <div class="mt-4 space-y-4" x-data="portalPay({
            invoices: @js($invoices->map(fn ($i) => ['id' => $i->id, 'outstanding' => $i->outstandingMinor()])->values()),
            cardEnabled: @js($cardEnabled),
        })">
        <x-card :title="__('portal.pay.invoices')">
            @if ($invoices->isEmpty())
                <p class="text-sm text-ink-muted">{{ __('portal.pay.no_invoices') }}</p>
            @else
                <ul class="divide-y divide-border">
                    @foreach ($invoices as $invoice)
                        <li class="flex items-center gap-3 py-3">
                            <input type="checkbox" id="pay-invoice-{{ $invoice->id }}" value="{{ $invoice->id }}" x-model="selected" :disabled="custom"
                                class="h-4 w-4 rounded border-border text-primary-600 focus:ring-primary-600">
                            <label for="pay-invoice-{{ $invoice->id }}" class="min-w-0 flex-1">
                                <span class="block text-sm font-medium text-ink">{{ $invoice->number }} · {{ $invoice->description ?? $invoice->type->label() }}</span>
                                <span class="block text-xs text-ink-muted">{{ __('portal.pay.due', ['date' => $invoice->due_date->format('j M Y')]) }}</span>
                            </label>
                            <span class="text-sm font-medium tabular-nums">{{ $invoice->outstanding()->format() }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif

            <div class="mt-4 border-t border-border pt-4">
                @if ($invoices->isNotEmpty())
                    <label class="inline-flex items-center gap-2 text-sm text-ink">
                        <input type="checkbox" x-model="custom" class="h-4 w-4 rounded border-border text-primary-600 focus:ring-primary-600">
                        {{ __('portal.pay.custom') }}
                    </label>
                @endif
                <div x-show="custom" class="mt-3">
                    <label for="custom-amount" class="mb-1 block text-sm font-medium text-ink">{{ __('portal.pay.amount') }}</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-ink-muted">{{ $currencySymbol }}</span>
                        <input id="custom-amount" type="text" inputmode="decimal" x-model="customDisplay" autocomplete="off"
                            class="block h-10 w-full rounded-control border border-border bg-surface pl-8 pr-3 text-right text-sm tabular-nums focus:border-primary-600 focus:ring-primary-600">
                    </div>
                </div>
                <p class="mt-3 flex items-baseline justify-between text-sm">
                    <span class="text-ink-muted">{{ __('portal.pay.amount') }}</span>
                    <span class="text-xl font-semibold tabular-nums text-ink" aria-live="polite">{{ $currencySymbol }}<span x-text="format(amount)"></span></span>
                </p>
            </div>
        </x-card>

        <x-card :title="__('portal.pay.method')">
            @if ($cardEnabled)
                <div class="grid gap-2 sm:grid-cols-2">
                    <label class="flex cursor-pointer items-start gap-3 rounded-control border p-3" :class="method === 'card' ? 'border-primary-600 bg-primary-50' : 'border-border'">
                        <input type="radio" value="card" x-model="method" class="mt-0.5 h-4 w-4 border-border text-primary-600 focus:ring-primary-600">
                        <span><span class="block text-sm font-medium text-ink">{{ __('portal.pay.card') }}</span><span class="block text-xs text-ink-muted">{{ __('portal.pay.card_hint') }}</span></span>
                    </label>
                    <label class="flex cursor-pointer items-start gap-3 rounded-control border p-3" :class="method === 'transfer' ? 'border-primary-600 bg-primary-50' : 'border-border'">
                        <input type="radio" value="transfer" x-model="method" class="mt-0.5 h-4 w-4 border-border text-primary-600 focus:ring-primary-600">
                        <span><span class="block text-sm font-medium text-ink">{{ __('portal.pay.transfer') }}</span><span class="block text-xs text-ink-muted">{{ __('portal.pay.transfer_hint') }}</span></span>
                    </label>
                </div>

                <form method="POST" action="{{ route('portal.pay.start') }}" x-show="method === 'card'" class="mt-4">
                    @csrf
                    <input type="hidden" name="amount" :value="amount">
                    <template x-for="id in invoiceIds" :key="id"><input type="hidden" name="invoice_ids[]" :value="id"></template>
                    <x-button class="w-full" icon="credit-card" x-bind:disabled="amount <= 0">{{ __('portal.pay.pay_online') }}</x-button>
                </form>
            @endif

            <div x-show="method === 'transfer'" @class(['mt-4' => $cardEnabled])>
                @if ($bank['account_number'])
                    <dl class="grid gap-2 rounded-control bg-canvas p-4 text-sm sm:grid-cols-3">
                        <div><dt class="text-ink-muted">{{ __('portal.pay.bank_name') }}</dt><dd class="font-medium text-ink">{{ $bank['name'] }}</dd></div>
                        <div><dt class="text-ink-muted">{{ __('portal.pay.account_name') }}</dt><dd class="font-medium text-ink">{{ $bank['account_name'] }}</dd></div>
                        <div><dt class="text-ink-muted">{{ __('portal.pay.account_number') }}</dt><dd class="font-medium tabular-nums text-ink">{{ $bank['account_number'] }}</dd></div>
                    </dl>
                @else
                    <x-alert type="info">{{ __('portal.pay.no_bank') }}</x-alert>
                @endif

                <form method="POST" action="{{ route('portal.pay.proof') }}" enctype="multipart/form-data" class="mt-4 space-y-4">
                    @csrf
                    <input type="hidden" name="amount" :value="amount">
                    <template x-for="id in invoiceIds" :key="id"><input type="hidden" name="invoice_ids[]" :value="id"></template>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-date-input name="paid_on" :label="__('portal.pay.paid_on')" :value="today()" required />
                        <x-input name="sender_name" :label="__('portal.pay.sender_name')" :value="$tenant->full_name" required />
                    </div>
                    <x-file-dropzone name="proof" :label="__('portal.pay.proof')" />
                    <x-button class="w-full" icon="arrow-up-tray" x-bind:disabled="amount <= 0">{{ __('portal.pay.submit_proof') }}</x-button>
                </form>
            </div>
        </x-card>

        @if ($pendingProofs->isNotEmpty())
            <x-card :title="__('portal.pay.pending')" :description="__('portal.pay.pending_hint')">
                <ul class="divide-y divide-border">
                    @foreach ($pendingProofs as $proof)
                        <li class="flex items-start justify-between gap-3 py-3 text-sm">
                            <div>
                                <p class="font-medium tabular-nums text-ink">{{ $proof->money()->format() }}</p>
                                <p class="text-xs text-ink-muted">{{ $proof->paid_at?->format('j M Y') }}</p>
                                @if ($proof->rejection_reason)
                                    <p class="text-xs text-danger">{{ __('portal.pay.rejected_reason', ['reason' => $proof->rejection_reason]) }}</p>
                                @endif
                            </div>
                            <x-status-chip :status="$proof->status" />
                        </li>
                    @endforeach
                </ul>
            </x-card>
        @endif
    </div>
</x-layouts::portal>
