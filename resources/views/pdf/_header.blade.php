<table class="header">
    <tr>
        <td>
            @if ($organization?->logo_path && is_file(public_path('uploads/'.$organization->logo_path)))
                <img src="{{ public_path('uploads/'.$organization->logo_path) }}" style="height: 40px;" alt="">
            @endif
            <div class="org">{{ $organization?->name }}</div>
            <div class="muted">{{ $organization?->address }}</div>
            <div class="muted">{{ $organization?->phone }} {{ $organization?->email }}</div>
        </td>
        <td class="title">{{ $documentTitle }}<br><span style="font-size: 12px; color: #202124;">{{ $documentNumber ?? '' }}</span></td>
    </tr>
</table>
