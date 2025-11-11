@php

    $hc = fn($i) => $hideClass($columnsMeta[$i]['hideOn'] ?? 'none');

    $isAdmin      = optional($item->sender)->role === 'admin';
    $senderName   = $isAdmin ? 'CBW Team' : ($item->sender->name ?? 'Unbekannt');
    $senderAvatar = $isAdmin
        ? asset('site-images/icon.png')
        : ($item->sender->profile_photo_url ?? asset('images/avatar-fallback.png'));

    $isUnread     = (int) $item->status === 1;

    $createdAbs   = optional($item->created_at)->format('d.m.Y H:i');
    $createdRel   = optional($item->created_at)?->diffForHumans();

    $snippet      = \Illuminate\Support\Str::limit(strip_tags($item->message), 100);
@endphp

{{-- 0: Von --}}
<div class="px-2 py-2 flex items-center gap-2 pr-4 {{ $hc(0) }}">
    <img src="{{ $senderAvatar }}" class="w-6 h-6 rounded-full object-cover" alt="">
    <div class="truncate">
        <span class="text-gray-900 {{ $isUnread ? 'font-semibold' : 'font-medium' }}">{{ $senderName }}</span>
        @if($isUnread)
            <span class="ml-1 inline-block w-1.5 h-1.5 rounded-full bg-blue-500 align-middle" aria-hidden="true"></span>
        @endif
    </div>
</div>

{{-- 1: Betreff --}}
<div class="px-2 py-2 flex flex-col min-w-0 {{ $hc(1) }}">
    <div class="truncate text-gray-900 {{ $isUnread ? 'font-semibold' : 'font-medium' }}">
        {{ $item->subject }}
    </div>
</div>

{{-- 2: Nachricht (Snippet + ggf. Anhang) --}}
<div class="px-2 py-2 min-w-0 text-gray-700 grid grid-cols-[auto_1fr] items-center gap-2 {{ $hc(2) }}">
    @if(($item->files_count ?? 0) > 0)
        <i class="far fa-paperclip text-gray-500"></i>
    @endif
    <div class="flex flex-col min-w-0">
    <span class="truncate">{{ $snippet }}</span>
</div>
</div>

{{-- 3: Datum --}}
<div class="px-2 py-2 text-xs text-gray-600  {{ $hc(3) }}" title="{{ $createdAbs }}">
    <div class="">
    {{ $createdRel }}
    </div>
</div>
