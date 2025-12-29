<div class="px-4 sm:px-6 lg:px-8">
    {{-- Grid --}}
    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 mt-6">

        {{-- Left: Kurse --}}
        <div class="lg:col-span-8 space-y-6">

            {{-- Card: Aktuelle Kurse --}}
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="h-1 w-full bg-gradient-to-r from-blue-600 via-indigo-600 to-purple-600"></div>

                <div class="p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h2 class="text-lg sm:text-xl font-extrabold text-gray-900 flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-blue-50 border border-blue-100 text-blue-700">
                                    <i class="fas fa-graduation-cap"></i>
                                </span>
                                <span class="truncate">Aktuelle Kurse</span>
                            </h2>
                            <p class="mt-1 text-sm text-gray-500">
                                Schnellzugriff auf deine aktiven Kurse und Details.
                            </p>
                        </div>

                        <a
                            href="{{ route('tutor.courses') }}"
                            wire:navigate
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            <i class="fas fa-list"></i>
                            <span class="hidden sm:inline">Alle Kurse</span>
                        </a>
                    </div>

                    <div class="mt-4">
                        <livewire:tutor.courses.courses-list-preview />
                    </div>

                </div>
            </div>

        </div>

        {{-- Right: Sidebar --}}
        <div class="lg:col-span-4 space-y-6" x-data="{ modalOpen:false, open:false, selectedMessage:null }">

            {{-- Card: Nachrichten --}}
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-base sm:text-lg font-extrabold text-gray-900 flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-indigo-50 border border-indigo-100 text-indigo-700">
                                    <i class="fas fa-envelope-open-text"></i>
                                </span>
                                <span class="truncate">Nachrichten</span>
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">Neueste Mitteilungen und Hinweise.</p>
                        </div>

                        <a
                            href="{{ route('messages') }}"
                            class="inline-flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-3 py-2 text-sm font-semibold text-gray-700 hover:bg-gray-50"
                        >
                            <i class="fas fa-inbox"></i>
                            <span class="hidden sm:inline">Alle</span>
                        </a>
                    </div>

                    @php
                        $receivedMessages = $receivedMessages ?? [];
                    @endphp

                    <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 bg-white">
                        @forelse($receivedMessages as $message)
                            <button
                                type="button"
                                @click="
                                    modalOpen = true;
                                    open = false;
                                    selectedMessage = {
                                        subject: @js($message->subject),
                                        body: @js($message->message),
                                        createdAt: @js($message->created_at->diffForHumans())
                                    };
                                    $wire.setMessageStatus({{ $message->id }});
                                "
                                class="w-full text-left flex items-start gap-3 p-4 hover:bg-gray-50 focus:outline-none focus:ring-2 focus:ring-blue-500/30"
                            >
                                <div class="shrink-0">
                                    <div class="h-10 w-10 rounded-xl border border-gray-200 bg-gray-50 flex items-center justify-center">
                                        <i class="fas fa-comment-dots text-gray-500"></i>
                                    </div>
                                </div>

                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center justify-between gap-3">
                                        <div class="font-semibold text-gray-900 truncate">
                                            {{ $message->subject }}
                                        </div>

                                        @if(($message->status ?? null) == 1)
                                            <span class="inline-flex items-center gap-1 rounded-full bg-blue-50 text-blue-700 ring-1 ring-blue-200 px-2 py-0.5 text-[11px] font-semibold">
                                                <i class="fas fa-circle text-[8px]"></i>
                                                Neu
                                            </span>
                                        @endif
                                    </div>

                                    <div class="mt-1 text-sm text-gray-600">
                                        {{ Str::limit(strip_tags($message->message), 70) }}
                                    </div>

                                    <div class="mt-2 text-[11px] text-gray-500 flex items-center gap-2">
                                        <i class="far fa-clock"></i>
                                        {{ $message->created_at->diffForHumans() }}
                                    </div>
                                </div>
                            </button>

                            @if(! $loop->last)
                                <div class="h-px bg-gray-200"></div>
                            @endif
                        @empty
                            <div class="p-6 text-center">
                                <div class="mx-auto h-12 w-12 rounded-2xl border border-gray-200 bg-gray-50 flex items-center justify-center text-gray-500">
                                    <i class="fas fa-inbox"></i>
                                </div>
                                <p class="mt-3 text-sm font-semibold text-gray-700">Keine Nachrichten</p>
                                <p class="mt-1 text-sm text-gray-500">Aktuell liegen keine neuen Mitteilungen vor.</p>
                            </div>
                        @endforelse
                    </div>

                    <div class="mt-4">
                        <a href="{{ route('messages') }}" class="inline-flex items-center gap-2 text-sm font-semibold text-blue-700 hover:underline">
                            Alle Nachrichten ansehen
                            <i class="fas fa-arrow-right text-[12px]"></i>
                        </a>
                    </div>
                </div>
            </div>

            {{-- Card: Nächste Termine --}}
            <div class="relative overflow-hidden rounded-2xl border border-gray-200 bg-white shadow-sm">
                <div class="p-5 sm:p-6">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <h3 class="text-base sm:text-lg font-extrabold text-gray-900 flex items-center gap-2">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-emerald-50 border border-emerald-100 text-emerald-700">
                                    <i class="fas fa-calendar-check"></i>
                                </span>
                                <span class="truncate">Nächste Termine</span>
                            </h3>
                            <p class="mt-1 text-sm text-gray-500">Anstehende Termine und Deadlines.</p>
                        </div>
                    </div>

                    {{-- Placeholder-Liste (hier später dynamisch füllen) --}}
                    <ul class="mt-4 space-y-2">
                        <li class="rounded-xl border border-gray-200 bg-gray-50/60 p-3 flex items-start justify-between gap-3">
                            <div class="flex items-start gap-3 min-w-0">
                                <span class="inline-flex h-9 w-9 items-center justify-center rounded-xl bg-white border border-gray-200 text-gray-600">
                                    <i class="fas fa-calendar-day"></i>
                                </span>
                                <div class="min-w-0">
                                    <div class="text-sm font-semibold text-gray-900 truncate">Keine Termine</div>
                                    <div class="text-sm text-gray-500 truncate">Aktuell ist nichts geplant.</div>
                                </div>
                            </div>
                            <span class="text-[11px] font-semibold text-gray-500 whitespace-nowrap">
                                —
                            </span>
                        </li>
                    </ul>
                </div>
            </div>

        </div>
    </div>
</div>
