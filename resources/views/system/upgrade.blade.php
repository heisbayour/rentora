<x-layouts::install :title="__('upgrade.title')">
    <h1 class="text-lg font-semibold text-ink">{{ __('upgrade.heading') }}</h1>
    <dl class="mt-5 grid grid-cols-2 gap-4 text-sm">
        <div>
            <dt class="text-ink-muted">{{ __('upgrade.code_version') }}</dt>
            <dd class="font-medium text-ink">{{ $codeVersion }}</dd>
        </div>
        <div>
            <dt class="text-ink-muted">{{ __('upgrade.installed_version') }}</dt>
            <dd class="font-medium text-ink">{{ $installedVersion ?? '-' }}</dd>
        </div>
    </dl>
    <h2 class="mt-6 text-sm font-medium text-ink">{{ __('upgrade.pending') }}</h2>
    @if ($pending === [])
        <p class="mt-1 text-sm text-ink-muted">{{ __('upgrade.none_pending') }}</p>
    @else
        <ul class="mt-2 space-y-1 rounded-control bg-canvas p-3 font-mono text-xs text-ink">
            @foreach ($pending as $migration)
                <li>{{ $migration }}</li>
            @endforeach
        </ul>
    @endif
    <form method="POST" action="{{ route('system.upgrade.run') }}" class="mt-6">
        @csrf
        <input type="hidden" name="key" value="{{ $key }}">
        <x-button>{{ __('upgrade.run') }}</x-button>
    </form>
</x-layouts::install>
