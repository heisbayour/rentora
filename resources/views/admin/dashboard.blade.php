<x-layouts::admin :title="__('dashboard.title')">
    <x-page-header :title="__('dashboard.greeting', ['name' => auth()->user()->name])" :description="__('dashboard.intro')" />
    <x-empty-state icon="chart-bar" :title="__('dashboard.empty_title')" :text="__('dashboard.empty_text')" />
</x-layouts::admin>
