<x-layouts::portal :title="__('messaging.inbox.title')">
    <h1 class="mb-4 text-xl font-semibold text-ink">{{ __('messaging.inbox.title') }}</h1>
    @include('messaging._list', [
        'indexRoute' => 'portal.inbox',
        'readRoute' => 'portal.inbox.read',
        'readAllRoute' => 'portal.inbox.read-all',
    ])
</x-layouts::portal>
