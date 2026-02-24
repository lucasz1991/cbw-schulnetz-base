<div>
    <div class="mb-6">
        <h1 class="text-2xl font-semibold text-slate-800">Hilfe & Kontakt</h1>
        <p class="mt-1 text-sm text-slate-600">Melde Probleme, technische Fehler oder Anmerkungen direkt an das Support-Team.</p>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-12">
        <aside class="xl:col-span-4">
            <div class="rounded-2xl border border-blue-100 bg-gradient-to-br from-blue-50 to-slate-50 p-6 shadow-sm">
                <h2 class="text-lg font-semibold text-slate-900">Wobei können wir helfen?</h2>
                <p class="mt-3 text-sm leading-relaxed text-slate-600">
                    Nutze dieses Formular für technische Probleme, Rückfragen zu Kursfunktionen oder allgemeine Hinweise.
                    Je genauer deine Beschreibung ist, desto schneller können wir reagieren.
                </p>

                <div class="mt-5 space-y-3 text-sm text-slate-700">
                    <div class="flex items-start gap-3">
                        <span class="mt-1 h-2.5 w-2.5 rounded-full bg-blue-600"></span>
                        <p>Beschreibe kurz, wo das Problem auftritt.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="mt-1 h-2.5 w-2.5 rounded-full bg-emerald-600"></span>
                        <p>Nenne wenn möglich Kurs, Bereich oder Funktion.</p>
                    </div>
                    <div class="flex items-start gap-3">
                        <span class="mt-1 h-2.5 w-2.5 rounded-full bg-slate-700"></span>
                        <p>Wir melden uns über deine hinterlegte E-Mail-Adresse.</p>
                    </div>
                </div>
            </div>
        </aside>

        <section class="xl:col-span-8">
            @if (session()->has('success'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('success') }}
                </div>
            @endif

            @if (session()->has('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
                    {{ session('error') }}
                </div>
            @endif

            <div class="rounded-2xl border border-slate-200 bg-white shadow-sm">
                <div class="p-6 md:p-8">
                    <div class="grid grid-cols-1 gap-5">
                        <div>
                            <x-label for="subject" value="Betreff" />
                            <x-input
                                id="subject"
                                name="subject"
                                type="text"
                                wire:model.defer="subject"
                                class="mt-2 block w-full"
                                placeholder="Kurzer Titel deines Anliegens"
                            />
                            @error('subject')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div>
                            <x-label for="message" value="Nachricht" />
                            <textarea
                                id="message"
                                name="message"
                                rows="10"
                                wire:model.defer="message"
                                class="mt-2 block w-full rounded-md border-slate-300 shadow-sm focus:border-blue-600 focus:ring-blue-600"
                                placeholder="Beschreibe dein Problem oder deine Anmerkung"
                            ></textarea>
                            @error('message')
                                <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                            @enderror
                        </div>

                        <div class="flex items-center justify-end pt-1">
                            <button
                                wire:click="send"
                                class="inline-flex items-center rounded-lg bg-blue-600 px-6 py-3 text-sm font-semibold text-white hover:bg-blue-700 transition"
                            >
                                Nachricht senden
                                <i class="fas fa-paper-plane ml-2"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </div>
</div>
