<div class="relative bg-white overflow-hidden isolate">
    {{-- Background --}}
    <div class="absolute inset-0 z-0 pointer-events-none">
        <x-ui.background.hero-background>
            <div class="min-h-[400px]"></div>
        </x-ui.background.hero-background>
    </div>

    {{-- Content --}}
    <main class="relative z-10 container mx-auto px-5 md:px-10 pt-8 pb-14">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">

            <aside class="lg:col-span-4" data-aos="fade-up">
                <div class="rounded-3xl p-[1px] bg-gradient-to-br from-blue-400 via-emerald-300 to-blue-200 shadow-[0_18px_60px_-40px_rgba(15,23,42,0.35)]">
                    <div class="rounded-3xl bg-white border border-white/60 overflow-hidden">
                        <div class="px-5 py-4 border-b border-slate-100 flex items-center justify-between">
                            <div>
                                <p class="text-sm font-semibold text-slate-900">Inhalte</p>
                            </div>
                        </div>

                        <div class="divide-y divide-slate-100">
                            @foreach($videos as $v)
                                @php
                                    $active = (int)$selectedVideoId === (int)$v['id'];
                                    $p = $v['progress'];
                                @endphp

                                <button
                                    type="button"
                                    wire:click="$set('selectedVideoId', {{ (int)$v['id'] }})"
                                    class="w-full text-left px-5 py-4 transition hover:bg-slate-50 {{ $active ? 'bg-slate-50' : '' }}"
                                >
                                    <div class="flex items-start gap-4">
                                        {{-- Icon --}}
                                        <div class="mt-0.5 h-10 w-10 shrink-0 rounded-2xl flex items-center justify-center
                                            {{ $v['is_pdf'] ? 'bg-slate-900 text-white' : 'bg-blue-600 text-white' }}">
                                            <i class="{{ $v['is_pdf'] ? 'fas fa-file-pdf' : 'fas fa-play' }}"></i>
                                        </div>

                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-start justify-between gap-3">
                                                <div class="min-w-0">
                                                    <div class="text-sm font-semibold text-slate-900 truncate">
                                                        {{ $v['title'] }}
                                                    </div>

                                                    <div class="mt-1 flex flex-wrap items-center gap-2">
                                                        @if($p['is_completed'])
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-50 text-emerald-800 ring-1 ring-emerald-100">
                                                                <span class="mr-2 h-2 w-2 rounded-full bg-emerald-500"></span>
                                                                Gesehen
                                                            </span>
                                                        @elseif($p['exists'] && $p['percent'] > 0)
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-50 text-amber-800 ring-1 ring-amber-100">
                                                                <span class="mr-2 h-2 w-2 rounded-full bg-amber-500"></span>
                                                                Teilweise ({{ $p['percent'] }}%)
                                                            </span>
                                                        @else
                                                            <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-slate-50 text-slate-700 ring-1 ring-slate-200">
                                                                <span class="mr-2 h-2 w-2 rounded-full bg-slate-400"></span>
                                                                Neu
                                                            </span>
                                                        @endif

                                                        @if(($v['duration_seconds'] ?? 0) > 0 && !$v['is_pdf'])
                                                            <span class="text-xs text-slate-500">
                                                                {{ gmdate('i:s', (int)$p['watched_seconds']) }} / {{ gmdate('i:s', (int)$v['duration_seconds']) }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>

                                                <i class="fas fa-chevron-right text-slate-300 mt-1"></i>
                                            </div>

                                            <div class="mt-3 h-2 w-full bg-slate-100 rounded-full overflow-hidden">
                                                <div class="h-full bg-gradient-to-r from-blue-600 to-emerald-600"
                                                     style="width: {{ (int)$p['percent'] }}%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </button>
                            @endforeach
                        </div>
                    </div>
                </div>
            </aside>

            {{-- PLAYER --}}
            <section class="lg:col-span-8" data-aos="fade-up" data-aos-delay="50">
                <div class="rounded-3xl p-[1px] bg-gradient-to-br from-blue-400 via-emerald-300 to-blue-200 shadow-[0_18px_60px_-40px_rgba(15,23,42,0.35)]">
                    <div class="rounded-3xl bg-white border border-white/60 overflow-hidden">
                        <div class="px-6 py-5 border-b border-slate-100 flex items-start justify-between gap-4">
                            <div class="min-w-0">
                                <div class="text-lg font-semibold text-slate-900 truncate">
                                    {{ $selected['title'] ?? '' }}
                                </div>

                                @if(($selected['progress']['is_completed'] ?? false) === true)
                                    <div class="mt-1 text-sm text-emerald-700 font-semibold">
                                        Status: vollständig gesehen
                                    </div>
                                @elseif(($selected['progress']['exists'] ?? false) === true && (($selected['progress']['percent'] ?? 0) > 0))
                                    <div class="mt-1 text-sm text-amber-700 font-semibold">
                                        Status: teilweise gesehen
                                    </div>
                                @else
                                  
                                @endif

                                @if(($startAtSeconds ?? 0) > 2 && (($selected['progress']['is_completed'] ?? false) === false) && !($selected['is_pdf'] ?? false))
                                    <div class="mt-2 text-xs text-slate-500">
                                        Fortsetzen bei {{ gmdate('i:s', (int)$startAtSeconds) }}
                                    </div>
                                @endif
                            </div>

                            <div class="shrink-0">
                                @if(($selected['is_pdf'] ?? false) === true)
                                    <span class="inline-flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700">
                                        <i class="fas fa-file-pdf mr-2 text-slate-500"></i>
                                        PDF
                                    </span>
                                @else
                                    <span class="inline-flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700">
                                        <i class="fas fa-play mr-2 text-slate-500"></i>
                                        Video
                                    </span>
                                @endif
                            </div>
                        </div>

                        <div class="p-6" wire:key="onboarding-player-{{ $selected['id'] ?? 'none' }}">
                            @if($selected && $selected['file_url'])

                                {{-- PDF --}}
                                @if($selected['is_pdf'])
                                    <div
                                        x-data="{ videoId: {{ (int)$selected['id'] }} }"
                                        x-init="$wire.markCompleted(videoId)"
                                        wire:key="onboarding-pdf-{{ (int)$selected['id'] }}"
                                        class="space-y-4"
                                    >
                                        <div class="rounded-2xl border border-slate-200 overflow-hidden bg-white shadow-sm">
                                            <iframe
                                                class="w-full h-[75vh]"
                                                src="{{ $selected['file_url'] }}"
                                            ></iframe>
                                        </div>

                                    </div>

                                @else
                                    <div
                                        x-data="{
                                            videoId: {{ (int)$selected['id'] }},
                                            startAt: {{ (int)$startAtSeconds }},
                                            lastSentAt: 0,
                                            throttleMs: 1000,
                                            completedSent: false,

                                            init() {
                                                const v = this.$refs.v;
                                                if (!v) return;

                                                v.addEventListener('loadedmetadata', () => {
                                                    if (this.startAt > 0 && this.startAt < (v.duration - 1)) {
                                                        v.currentTime = this.startAt;
                                                    }
                                                });

                                                v.addEventListener('timeupdate', () => {
                                                    const now = Date.now();
                                                    if (now - this.lastSentAt < this.throttleMs) return;
                                                    this.lastSentAt = now;

                                                    const cur = Math.floor(v.currentTime || 0);
                                                    const dur = Math.floor(v.duration || 0);

                                                    this.$wire.saveProgress(this.videoId, cur, dur);

                                                    if (!this.completedSent && dur > 0 && cur >= (dur - 1)) {
                                                        this.completedSent = true;
                                                        this.$wire.markCompleted(this.videoId, dur);
                                                    }
                                                });

                                                v.addEventListener('ended', () => {
                                                    this.completedSent = true;

                                                    const dur = Math.floor(v.duration || 0);
                                                    this.$wire.saveProgress(this.videoId, dur, dur);
                                                    this.$wire.markCompleted(this.videoId, dur);
                                                });
                                            }
                                        }"
                                        wire:key="onboarding-video-{{ (int)$selected['id'] }}"
                                        class="space-y-4"
                                    >
                                        <div class="rounded-2xl border border-slate-200 overflow-hidden bg-black shadow-sm">
                                            <video
                                                x-ref="v"
                                                class="w-full"
                                                controls
                                                playsinline
                                                preload="metadata"
                                                src="{{ $selected['file_url'] }}"
                                            ></video>
                                        </div>

                                    </div>
                                @endif

                            @elseif($selected)
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                    Für dieses Element wurde keine abspielbare Datei/URL gefunden.
                                </div>
                            @else
                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                                    Leider wurde bisher noch kein Inhalt zur Verfügung gestellt.
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            </section>

        </div>
    </main>
</div>
