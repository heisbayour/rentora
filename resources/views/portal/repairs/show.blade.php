<x-layouts::portal :title="$request->title">
    <a href="{{ route('portal.repairs.index') }}" class="mb-4 inline-flex items-center gap-1 text-sm text-ink-muted hover:text-ink">
        <x-heroicon-m-arrow-left class="h-4 w-4" aria-hidden="true" /> {{ __('maintenance.portal.title') }}
    </a>

    <x-card>
        <div class="flex items-start justify-between gap-3">
            <div>
                <h1 class="text-lg font-semibold text-ink">{{ $request->title }}</h1>
                <p class="text-xs text-ink-muted">{{ $request->number }} · {{ $request->created_at->format('j M Y') }}</p>
            </div>
            <x-status-chip :status="$request->status" />
        </div>
        <p class="mt-4 whitespace-pre-line text-sm text-ink">{{ $request->description }}</p>
        @if ($request->scheduled_for)
            <p class="mt-3 text-sm"><span class="text-ink-muted">{{ __('maintenance.details.scheduled') }}:</span> {{ $request->scheduled_for->format('j M Y, H:i') }}</p>
        @endif
        @if ($request->attachments->isNotEmpty())
            <ul class="mt-4 grid grid-cols-3 gap-2">
                @foreach ($request->attachments as $photo)
                    <li><img src="{{ route('files.maintenance', $photo) }}" alt="" class="h-20 w-full rounded-control object-cover" loading="lazy"></li>
                @endforeach
            </ul>
        @endif
        @can('cancel', $request)
            <x-button type="button" variant="ghost" class="mt-4 text-danger" x-data x-on:click="$dispatch('open-modal', 'cancel-repair')">{{ __('maintenance.portal.cancel') }}</x-button>
        @endcan
    </x-card>

    @can('rate', $request)
        <x-card :title="__('maintenance.portal.confirm_fixed')" :description="__('maintenance.portal.confirm_fixed_intro')" class="mt-6">
            <form method="POST" action="{{ route('portal.repairs.rate', $request) }}" class="space-y-4">
                @csrf
                <x-select name="rating" :label="__('maintenance.portal.rating')" value="5"
                    :options="collect(range(5, 1))->mapWithKeys(fn ($n) => [$n => trans_choice('maintenance.portal.stars', $n, ['count' => $n])])->all()" />
                <x-textarea name="feedback" :label="__('maintenance.portal.feedback')" rows="2" optional />
                <x-button>{{ __('maintenance.portal.submit_rating') }}</x-button>
            </form>
        </x-card>
    @endcan

    <x-card :title="__('maintenance.details.thread')" class="mt-6">
        @can('comment', $request)
            <form method="POST" action="{{ route('portal.repairs.comment', $request) }}" enctype="multipart/form-data" class="mb-6 space-y-3">
                @csrf
                <x-textarea name="body" :label="__('maintenance.fields.comment')" rows="2" required />
                <x-button size="sm">{{ __('maintenance.actions.comment') }}</x-button>
            </form>
        @endcan
        @include('maintenance._thread', ['updates' => $updates])
    </x-card>

    @can('cancel', $request)
        <x-confirm name="cancel-repair" method="POST" :action="route('portal.repairs.cancel', $request)"
            :title="__('maintenance.portal.cancel_title')" :consequences="__('maintenance.portal.cancel_consequences')" :confirm-label="__('maintenance.portal.cancel')" />
    @endcan
</x-layouts::portal>
