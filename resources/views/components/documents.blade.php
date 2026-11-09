{{-- Documents panel for any documentable record: list, download, upload, delete. --}}
@props([
    'documentable',
    'documents',
    'visibilities' => [],
    'canManage' => false,
])

@php
    $type = $documentable->getMorphClass();
    $modal = 'upload-document-'.$type.'-'.$documentable->getKey();
@endphp

<div {{ $attributes }}>
    @if ($canManage)
        <div class="mb-4 flex justify-end">
            <x-button type="button" size="sm" icon="arrow-up-tray" x-data x-on:click="$dispatch('open-modal', @js($modal))">{{ __('documents.upload') }}</x-button>
        </div>
    @endif

    @if ($documents->isEmpty())
        <x-empty-state icon="document" :title="__('documents.empty_title')" :text="__('documents.empty_text')" />
    @else
        <x-table :caption="__('documents.title')">
            <x-slot:head>
                <th>{{ __('documents.name') }}</th>
                <th>{{ __('documents.visibility') }}</th>
                <th>{{ __('documents.size') }}</th>
                <th>{{ __('documents.uploaded_on') }}</th>
                <th><span class="sr-only">{{ __('documents.download') }}</span></th>
            </x-slot:head>
            @foreach ($documents as $document)
                <tr>
                    <td data-label="{{ __('documents.name') }}" class="font-medium">{{ $document->name }}</td>
                    <td data-label="{{ __('documents.visibility') }}"><x-status-chip :status="$document->visibility" /></td>
                    <td data-label="{{ __('documents.size') }}" class="tabular-nums text-ink-muted">{{ $document->humanSize() }}</td>
                    <td data-label="{{ __('documents.uploaded_on') }}" class="text-ink-muted">{{ $document->created_at?->format('j M Y') }}</td>
                    <td data-label="" class="text-right">
                        <div class="flex justify-end gap-1">
                            <x-button size="sm" variant="ghost" :href="route('files.documents', $document)">{{ __('documents.download') }}</x-button>
                            @if ($canManage)
                                <x-button type="button" size="sm" variant="ghost" class="text-danger" x-data x-on:click="$dispatch('open-modal', 'delete-document-{{ $document->id }}')">{{ __('documents.delete') }}</x-button>
                            @endif
                        </div>
                    </td>
                </tr>
            @endforeach
        </x-table>
    @endif

    @if ($canManage)
        <x-modal :name="$modal" :title="__('documents.upload_title')" :show="$errors->has('file')">
            <form method="POST" action="{{ route('app.documents.store') }}" enctype="multipart/form-data" class="space-y-4">
                @csrf
                <input type="hidden" name="documentable_type" value="{{ $type }}">
                <input type="hidden" name="documentable_id" value="{{ $documentable->getKey() }}">
                <x-file-dropzone name="file" :label="__('documents.file')" />
                <x-input name="name" :label="__('documents.name')" :hint="__('documents.name_hint')" optional />
                <x-select name="visibility" :label="__('documents.visibility')" :options="$visibilities" value="internal" />
                <div class="flex justify-end gap-2 pt-2">
                    <x-button type="button" variant="secondary" x-on:click="$dispatch('close-modal', @js($modal))">{{ __('ui.cancel') }}</x-button>
                    <x-button>{{ __('documents.upload') }}</x-button>
                </div>
            </form>
        </x-modal>
        @foreach ($documents as $document)
            <x-confirm :name="'delete-document-'.$document->id" :action="route('app.documents.destroy', $document)"
                :title="__('documents.delete_title', ['name' => $document->name])" :consequences="__('documents.delete_consequences')"
                :confirm-label="__('documents.delete')" />
        @endforeach
    @endif
</div>
