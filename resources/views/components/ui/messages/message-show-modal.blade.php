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
            <div class="space-y-6 border border-gray-200 rounded-lg p-6 bg-gray-50  mt-6 mb-4">
                {{-- Subject --}}
                <h3 class="text-xl font-semibold mb-1 border-b pb-2">
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
            </div>

            {{-- Anhänge --}}
            @if($message->files?->count())
                <div class="mt-6 px-2">
                    <h4 class="text-sm font-semibold mb-2 flex items-center gap-2">
                        <svg class="w-4 h-4" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M18.364 5.636a5 5 0 010 7.071l-7.071 7.071a5 5 0 11-7.071-7.071l6-6a3 3 0 114.243 4.243l-6 6a1 1 0 11-1.414-1.414l6-6a1 1 0 10-1.414-1.414l-6 6a3 3 0 104.243 4.243l7.071-7.071a3 3 0 10-4.243-4.243l-6 6" />
                        </svg>
                        Anhänge ({{ $message->files->count() }})
                    </h4>

                    <div class="my-8 mx-2">
                        <ul class="space-y-2">
                        @foreach($message->files as $f)
                            <li class="flex items-center justify-between gap-3 border rounded px-3 py-2">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img src="{{ $f->icon_or_thumbnail }}" class="h-8 w-8 rounded object-cover border" alt="">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium">{{ $f->name_with_extension }}</div>
                                        <div class="text-xs text-gray-500">{{ $f->getMimeTypeForHumans() }} · {{ $f->size_formatted }}</div>
                                    </div>
                                </div>
                                <div class="shrink-0 flex items-center gap-2">
                                    {{-- Vorschau --}}
                                    <button 
                                        type="button"
                                        title="Vorschau"
                                        @click="window.dispatchEvent(new CustomEvent('filepool-preview', { detail: { id: {{ $f->id }} } }))"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm border rounded-lg bg-white hover:bg-gray-50 text-gray-700"
                                    >
                                        <i class="fal fa-eye"></i>
                                        <span>Vorschau</span>
                                    </button>

                                    {{-- Öffnen (temporäre URL) --}}
                                    <a 
                                        href="{{ $f->getEphemeralPublicUrl(10) }}" 
                                        target="_blank" 
                                        title="In neuem Tab öffnen"
                                        class="inline-flex items-center gap-2 px-3 py-1.5 text-sm border rounded-lg bg-white hover:bg-gray-50 text-gray-700"
                                    >
                                        <i class="fal fa-external-link-alt"></i>
                                        <span>Öffnen</span>
                                    </a>
                                </div>
                            </li>
                        @endforeach
                        </ul>
                    </div>
                </div>
                @else
                    <div class="text-sm text-gray-500">Keine Anhänge.</div>
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
