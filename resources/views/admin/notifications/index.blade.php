<x-layouts::admin :title="__('messaging.inbox.title')">
    <x-page-header :title="__('messaging.inbox.title')" />
    @include('messaging._list', [
        'indexRoute' => 'app.notifications.index',
        'readRoute' => 'app.notifications.read',
        'readAllRoute' => 'app.notifications.read-all',
    ])
</x-layouts::admin>
