<x-layouts::portal :title="__('portal.payments.title')">
    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-xl font-semibold text-ink">{{ __('portal.payments.title') }}</h1>
        <x-button size="sm" variant="secondary" :href="route('portal.statement')" icon="document-chart-bar">{{ __('portal.payments.statement') }}</x-button>
    </div>

    @if ($payments->isEmpty())
        <x-empty-state icon="banknotes" :title="__('portal.payments.empty_title')" :text="__('portal.payments.empty_text')"
            :action-label="__('portal.home.pay_now')" :action-href="route('portal.pay')" />
    @else
        <ul class="space-y-3">
            @foreach ($payments as $payment)
                <li class="rounded-card border border-border bg-surface p-4 shadow-card">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <p class="font-semibold tabular-nums text-ink">{{ $payment->money()->format() }}</p>
                            <p class="text-xs text-ink-muted">{{ $payment->method->label() }} · {{ ($payment->paid_at ?? $payment->created_at)->format('j M Y') }}</p>
                            @if ($payment->receipt_number)
                                <p class="text-xs text-ink-muted">{{ $payment->receipt_number }}</p>
                            @endif
                        </div>
                        <x-status-chip :status="$payment->status" />
                    </div>
                    @if ($payment->status === \App\Enums\PaymentStatus::Successful)
                        <x-button size="sm" variant="ghost" class="mt-2" :href="route('portal.payments.receipt', $payment)" icon="arrow-down-tray">{{ __('portal.payments.receipt') }}</x-button>
                    @elseif ($payment->status === \App\Enums\PaymentStatus::Pending && $payment->method === \App\Enums\PaymentMethod::Paystack)
                        <form method="POST" action="{{ route('portal.payments.check', $payment) }}" class="mt-2">
                            @csrf
                            <x-button size="sm" variant="ghost" icon="arrow-path">{{ __('payments.check') }}</x-button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
        <x-pagination :paginator="$payments" class="mt-4" />
    @endif
</x-layouts::portal>
