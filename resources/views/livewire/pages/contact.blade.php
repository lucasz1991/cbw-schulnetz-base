<div class="min-h-screen bg-white">
    <x-ui.background.hero-background>
        <div class="relative container mx-auto px-5 pb-8 pt-14 md:px-10">
            <div class="mx-auto max-w-4xl" data-aos="fade-up">
                <a href="/" class="inline-flex items-center gap-2">
                    <x-application-logo />
                </a>

                <h1 class="mt-5 text-4xl font-semibold tracking-tight text-slate-900 md:text-5xl">
                    Technische Anfrage
                </h1>

                <p class="mt-4 max-w-3xl text-base leading-relaxed text-slate-600 md:text-lg">
                    Technische Anfragen aus dem Teilnehmerbereich werden ausschließlich als Ticket an die IT-Abteilung übermittelt.
                </p>
            </div>
        </div>
    </x-ui.background.hero-background>

    <main class="container mx-auto px-5 pb-14 md:px-10">
        <section class="mx-auto max-w-4xl" data-aos="fade-up">
            <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-[0_18px_60px_-40px_rgba(15,23,42,0.35)] md:p-8">
                <div class="rounded-2xl border border-blue-200 bg-blue-50 px-4 py-4 text-sm leading-relaxed text-blue-900">
                    <div class="flex items-start gap-3">
                        <span class="mt-0.5 inline-flex h-8 w-8 items-center justify-center rounded-full bg-blue-600 text-white">
                            <i class="fas fa-headset text-sm"></i>
                        </span>
                        <div>
                            <p class="mt-1">
                                Deine Meldung wird direkt als IT-Ticket erfasst und an die zentrale IT-Abteilung weitergeleitet.
                            </p>
                        </div>
                    </div>
                </div>

                @if (session()->has('success'))
                    <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                        {{ session('success') }}
                    </div>
                @endif

                @if (session()->has('error'))
                    <div class="mt-6 rounded-2xl border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                        {{ session('error') }}
                    </div>
                @endif

          

                <div class="mt-5">
                    <div class="">
                        <x-label for="subject" value="Ticket-Titel" />
                        <x-input
                            wire:model.defer="subject"
                            id="subject"
                            type="text"
                            class="mt-2 block w-full rounded-2xl"
                            placeholder="Kurze Zusammenfassung des Problems"
                        />
                        @error('subject')
                            <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                        @enderror
                    </div>

                </div>

                <div class="mt-5">
                    <x-label for="message" value="Problembeschreibung" />
                    <textarea
                        wire:model.defer="message"
                        id="message"
                        name="message"
                        rows="8"
                        class="mt-2 block w-full rounded-2xl border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                        placeholder="Beschreibe das technische Problem so konkret wie möglich."
                    ></textarea>
                    @error('message')
                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>

                <div class="mt-6 flex flex-col gap-4 border-t border-slate-200 pt-5 sm:flex-row sm:items-center sm:justify-end">

                    <button
                        wire:click="send"
                        class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-blue-600 to-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-sm transition hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-blue-200"
                    >
                        Ticket erstellen
                        <i class="fas fa-paper-plane ml-2"></i>
                    </button>
                </div>
            </div>
        </section>
    </main>
</div>
