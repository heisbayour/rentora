<x-layouts::install :title="__('reset.title')">
    @if ($summary)
        <h1 class="text-lg font-semibold text-ink">{{ __('reset.summary_title') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('reset.summary_intro') }}</p>

        <dl class="mt-5 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            @foreach ($summary['counts'] as $item => $count)
                <div class="flex justify-between gap-4 border-b border-border py-1.5">
                    <dt class="text-ink-muted">{{ __('reset.labels.'.$item) }}</dt>
                    <dd class="font-medium tabular-nums text-ink">{{ number_format($count) }}</dd>
                </div>
            @endforeach
        </dl>

        @if ($summary['new_owner'])
            <x-alert type="warning" :title="__('reset.new_owner_created')" class="mt-6">
                <p>{{ $summary['new_owner']['name'] }}, {{ $summary['new_owner']['email'] }}</p>
                <p class="mt-1">{{ __('reset.temporary_password') }}:
                    <code class="select-all rounded bg-surface px-1.5 py-0.5 font-mono text-sm" data-temporary-password>{{ $summary['new_owner']['password'] }}</code>
                </p>
                <p class="mt-1">{{ __('reset.password_once') }}</p>
            </x-alert>
        @endif

        <div class="mt-6">
            <x-button :href="route('app.dashboard')">{{ __('reset.back') }}</x-button>
        </div>
    @else
        <h1 class="text-lg font-semibold text-ink">{{ __('reset.heading') }}</h1>
        <p class="mt-1 text-sm text-ink-muted">{{ __('reset.intro', ['organization' => $organization->name]) }}</p>

        <x-alert type="danger" :title="__('reset.warning_title')" class="mt-5">
            {{ __('reset.warning') }}
        </x-alert>

        <h2 class="mt-6 text-sm font-medium text-ink">{{ __('reset.will_delete') }}</h2>
        <dl class="mt-2 grid grid-cols-1 gap-x-6 gap-y-1 text-sm sm:grid-cols-2">
            @foreach ($counts as $item => $count)
                <div class="flex justify-between gap-4 border-b border-border py-1.5">
                    <dt class="text-ink-muted">{{ __('reset.labels.'.$item) }}</dt>
                    <dd class="font-medium tabular-nums text-ink" data-count="{{ $item }}">{{ number_format($count) }}</dd>
                </div>
            @endforeach
        </dl>

        <h2 class="mt-6 text-sm font-medium text-ink">{{ __('reset.kept_title') }}</h2>
        <p class="mt-1 text-sm text-ink-muted">{{ __('reset.kept') }}</p>

        <form method="POST" action="{{ route('system.reset.run') }}" class="mt-6 space-y-5"
            x-data="{ submitting: false }" x-on:submit="submitting = true">
            @csrf
            <input type="hidden" name="key" value="{{ $key }}">

            <fieldset class="space-y-4 rounded-control border border-border p-4">
                <legend class="px-1 text-sm font-medium text-ink">{{ __('reset.new_owner_title') }}</legend>
                <p class="text-xs text-ink-muted">{{ __('reset.new_owner_hint') }}</p>
                <x-input name="new_owner_name" :label="__('reset.new_owner_name')" optional autocomplete="off" />
                <x-input name="new_owner_email" type="email" :label="__('reset.new_owner_email')" optional autocomplete="off" />
            </fieldset>

            <x-input name="confirm" :label="__('reset.confirm_label')" autocomplete="off" required />
            <x-input name="password" type="password" :label="__('reset.password_label')" autocomplete="current-password" required />

            <x-button variant="danger" x-bind:disabled="submitting">{{ __('reset.run') }}</x-button>
        </form>
    @endif
</x-layouts::install>
