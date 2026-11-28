@php($tz = config('rentora.defaults.timezone'))
@php($when = fn (?string $iso) => $iso ? \Illuminate\Support\Carbon::parse($iso)->timezone($tz)->format('j M Y H:i') : __('health.never'))
<x-layouts::admin :title="__('health.title')">
    <x-page-header :title="__('health.title')" :description="__('health.description')" />

    @if ($debug)
        <x-alert type="warning" class="mb-4">{{ __('health.debug_on') }}</x-alert>
    @endif

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <x-card :title="__('health.version')">
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('settings.version.version') }}</dt><dd class="font-medium tabular-nums">{{ $version['version'] }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('settings.version.commit') }}</dt><dd class="font-mono text-xs">{{ $version['commit'] ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-muted">{{ __('settings.version.built_at') }}</dt><dd class="text-xs">{{ $version['built_at'] ?? '-' }}</dd></div>
                <div class="flex justify-between gap-3"><dt class="text-ink-muted">PHP</dt><dd class="tabular-nums">{{ $phpVersion }}</dd></div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-muted">{{ __('health.database') }}</dt>
                    <dd><x-status-chip :label="$database['ok'] ? __('health.ok') : __('health.problem')" :color="$database['ok'] ? 'success' : 'danger'" /> <span class="text-xs text-ink-muted">{{ $database['detail'] }}</span></dd>
                </div>
                <div class="flex justify-between gap-3">
                    <dt class="text-ink-muted">{{ __('health.pending_migrations') }}</dt>
                    <dd>
                        @if ($pending === [])
                            <x-status-chip :label="__('health.none')" color="success" />
                        @else
                            <a href="{{ route('system.upgrade') }}" class="font-medium text-warning hover:underline">{{ count($pending) }}</a>
                        @endif
                    </dd>
                </div>
            </dl>
        </x-card>

        <x-card :title="__('health.requirements')">
            <ul class="space-y-1 text-sm">
                @foreach ($checks as $check)
                    <li class="flex items-center justify-between gap-3">
                        <span>{{ $check['label'] }}</span>
                        <x-status-chip :label="$check['detail']" :color="$check['ok'] ? 'success' : 'danger'" />
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card :title="__('health.connections.title')" class="lg:col-span-2" id="connections">
            <p class="mb-3 text-sm text-ink-muted">{{ __('health.connections.intro') }}</p>
            <ul class="divide-y divide-border">
                @foreach ($connections as $kind => $connection)
                    @php($color = ['working' => 'success', 'failing' => 'danger', 'not_set_up' => 'neutral'][$connection['status']] ?? 'warning')
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3" data-connection="{{ $kind }}">
                        <div class="min-w-0">
                            <p class="font-medium text-ink">{{ __('health.connections.kinds.'.$kind) }}</p>
                            <p class="mt-1 flex flex-wrap items-center gap-2 text-sm">
                                <x-status-chip :label="__('health.connections.status.'.$connection['status'])" :color="$color" />
                                @if ($connection['status'] !== 'not_set_up' && $connection['checked_at'])
                                    <span class="text-xs text-ink-muted">{{ __('health.connections.checked', ['when' => $connection['checked_at']->timezone($tz)->format('j M Y H:i')]) }}</span>
                                @endif
                                @if ($connection['status'] === 'working' && $connection['detail'])
                                    <span class="text-xs text-ink-muted">· {{ $connection['detail'] }}</span>
                                @endif
                            </p>
                            @if ($connection['status'] === 'failing' && $connection['error'])
                                <p class="mt-1 break-words text-xs text-danger">{{ $connection['error'] }}</p>
                            @endif
                        </div>
                        <form method="POST" action="{{ route('app.health.connections.test', $kind) }}">
                            @csrf
                            <x-button size="sm" variant="secondary" icon="signal">{{ __('health.connections.test') }}</x-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        </x-card>

        <x-card :title="__('health.scheduler')" class="lg:col-span-2">
            <p class="mb-3 text-sm">
                <span class="text-ink-muted">{{ __('health.mode') }}:</span> <span class="font-medium">{{ $schedulerMode }}</span>
                · <span class="text-ink-muted">{{ __('health.last_tick') }}:</span> <span class="font-medium">{{ $when($lastTick) }}</span>
            </p>
            <x-table :caption="__('health.scheduler')">
                <x-slot:head>
                    <th>{{ __('health.task') }}</th>
                    <th>{{ __('health.frequency') }}</th>
                    <th>{{ __('health.last_run') }}</th>
                    <th>{{ __('health.last_error') }}</th>
                </x-slot:head>
                @foreach ($tasks as $task)
                    <tr>
                        <td data-label="{{ __('health.task') }}"><code class="text-xs">{{ $task['name'] }}</code></td>
                        <td data-label="{{ __('health.frequency') }}">{{ $task['every'] }}</td>
                        <td data-label="{{ __('health.last_run') }}">{{ $when($task['last_run']) }}</td>
                        <td data-label="{{ __('health.last_error') }}" class="max-w-xs truncate text-xs text-danger">{{ $task['error'] }}</td>
                    </tr>
                @endforeach
            </x-table>
        </x-card>

        <x-card :title="__('health.outbox')" class="lg:col-span-2">
            <x-slot:actions>
                <form method="POST" action="{{ route('app.health.drain') }}">@csrf<x-button size="sm" variant="secondary" icon="paper-airplane">{{ __('health.drain_now') }}</x-button></form>
                <form method="POST" action="{{ route('app.health.retry') }}">@csrf<x-button size="sm" variant="secondary" icon="arrow-path">{{ __('health.retry_failed') }}</x-button></form>
            </x-slot:actions>
            <div class="mb-2 flex flex-wrap gap-3">
                @foreach ($outbox as $status => $count)
                    <x-status-chip :label="\App\Enums\MessageStatus::from($status)->label().': '.$count" :color="\App\Enums\MessageStatus::from($status)->color()" />
                @endforeach
            </div>
            <p class="mb-4 text-sm text-ink-muted">{{ __('health.outbox_help') }}</p>
            @if ($failed->isNotEmpty())
                <h3 class="mb-2 text-sm font-semibold">{{ __('health.failed_messages') }}</h3>
                <ul class="space-y-2 text-sm">
                    @foreach ($failed as $message)
                        <li class="rounded-control bg-danger-bg px-3 py-2">
                            <span class="font-medium">{{ strtoupper($message->channel) }}</span> {{ $message->recipient }} · {{ $message->subject ?? $message->event }}
                            <span class="block text-xs text-danger">{{ $message->last_error }}</span>
                        </li>
                    @endforeach
                </ul>
            @endif
        </x-card>
    </div>
</x-layouts::admin>
