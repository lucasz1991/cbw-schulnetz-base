@props([
    'model'     => null,   // Livewire-Property-Name, z. B. "showMessageModal"
    'message'   => null,
    'teamName'  => 'CBW Team',
    'teamLogo'  => asset('site-images/icon.png'),
])

@php
    $isAdmin      = optional($message?->sender)->role === 'admin';
    $senderName   = $isAdmin ? $teamName : ($message?->sender?->name ?? 'Unbekannt');
    $senderAvatar = $isAdmin
        ? $teamLogo
        : ($message?->sender?->profile_photo_url ?? asset('images/avatar-fallback.png'));
    $createdAbs   = $message?->created_at?->format('d.m.Y H:i');
    $createdRel   = $message?->created_at?->diffForHumans();
    $subject      = $message?->subject ?? 'Nachricht';
    $header       = $message?->header ?? null;
@endphp

<x-dialog-modal :wire:model="$model" :maxWidth="'4xl'">
    {{-- Titel: Absender + Zeitpunkt --}}
    <x-slot name="title">
        @if($message)
            <div class="flex items-center gap-3">
                <img src="{{ $senderAvatar }}" class="w-8 h-8 rounded-full object-cover" alt="">
                <div class="min-w-0">
                    <div class="font-medium leading-tight truncate">{{ $senderName }}</div>
                    <div class="text-xs text-gray-500 truncate" title="{{ $createdAbs }}">
                        {{ $createdRel }}
                    </div>
                </div>
            </div>
        @else
            <span class="font-semibold">Nachricht</span>
        @endif
    </x-slot>

    {{-- Inhalt --}}
    <x-slot name="content">
        @if($message)
            {{-- Subject --}}
            <h3 class="text-xl font-semibold mb-1 border-b pb-2 mt-12">
                {{ $subject }}
            </h3>

            {{-- Header (Nachrichtenüberschrift) --}}
            @if($header)
                <div class="text-gray-700 font-medium mb-4">
                    {{ $header }}
                </div>
            @endif

            {{-- Nachrichtentext --}}
            <div class="prose prose-sm max-w-none text-gray-800">
                {!! $message->message !!}
            </div>

            {{-- Anhänge --}}
            @if($message->files?->count())
                <div class="mt-12">
                    <h4 class="text-sm font-semibold mb-2 flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M18.364 5.636a5 5 0 010 7.071l-7.071 7.071a5 5 0 11-7.071-7.071l6-6a3 3 0 114.243 4.243l-6 6a1 1 0 11-1.414-1.414l6-6a1 1 0 10-1.414-1.414l-6 6a3 3 0 104.243 4.243l7.071-7.071a3 3 0 10-4.243-4.243l-6 6" />
                        </svg>
                        Anhänge ({{ $message->files->count() }})
                    </h4>
                    <ul class="space-y-1">
                        @foreach($message->files as $file)
                            <li class="truncate">
                                <a href="{{ $file->getEphemeralPublicUrl() }}"
                                   target="_blank"
                                   class="text-blue-600 hover:underline break-all">
                                    {{ $file->name }}
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        @else
            <div class="text-sm text-gray-500">Keine Nachricht ausgewählt.</div>
        @endif
    </x-slot>

    {{-- Footer --}}
    <x-slot name="footer">
        <button
            type="button"
            class="bg-gray-200 hover:bg-gray-300 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg"
            wire:click="$set('{{ $model }}', false)"
        >
            Schließen
        </button>
    </x-slot>
</x-dialog-modal>
