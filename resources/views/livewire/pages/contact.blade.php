<div class="min-h-screen bg-white">
    {{-- HERO / INTRO --}}
    <section class="relative overflow-hidden">
        {{-- background gradient + glows --}}
        <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-white to-emerald-50"></div>
        <div class="absolute -top-24 -right-24 h-80 w-80 rounded-full bg-blue-200/50 blur-3xl"></div>
        <div class="absolute -bottom-28 -left-28 h-80 w-80 rounded-full bg-emerald-200/50 blur-3xl"></div>

        {{-- wave bottom --}}
        <svg class="absolute bottom-0 left-0 right-0 w-full text-white" viewBox="0 0 1440 120" preserveAspectRatio="none">
            <path fill="currentColor" d="M0,64L60,69.3C120,75,240,85,360,80C480,75,600,53,720,42.7C840,32,960,32,1080,42.7C1200,53,1320,75,1380,85.3L1440,96L1440,120L1380,120C1320,120,1200,120,1080,120C960,120,840,120,720,120C600,120,480,120,360,120C240,120,120,120,60,120L0,120Z"></path>
        </svg>

        <div class="relative container mx-auto px-5 md:px-10 pt-14 pb-16">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-start lg:justify-between">
                <div class="min-w-0" data-aos="fade-up">
                    <a href="/" class="inline-flex items-center gap-2">
                        <x-application-logo/>
                    </a>


                    <h1 class="mt-5 text-4xl md:text-5xl font-semibold tracking-tight text-slate-900">
                        Kontakt
                    </h1>

                    <p class="mt-4 max-w-2xl text-base md:text-lg leading-relaxed text-slate-600">
                        Vielen Dank für Ihr Interesse an CBW Schulnetz! Wenn Sie Fragen zu unseren Bildungsangeboten haben,
                        Unterstützung bei der Nutzung unserer Plattform benötigen oder weitere Informationen rund um unsere Services wünschen,
                        stehen wir Ihnen gerne zur Verfügung.
                    </p>

                    <div class="mt-7 flex flex-wrap gap-3">
                        <a href="mailto:info@cbw-weiterbildung.de"
                           class="inline-flex items-center rounded-2xl bg-secondary px-5 py-3 text-sm font-semibold text-white shadow-sm hover:bg-secondary-dark transition">
                            <i class="fas fa-envelope mr-2"></i>
                            info@cbw-weiterbildung.de
                        </a>

                        <span class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm">
                            <i class="fas fa-clock mr-2 text-slate-500"></i>
                            Werktags erreichbar
                        </span>
                    </div>
                </div>

                {{-- Hero image card (no overlay) --}}
                <div class="w-full lg:w-[420px]" data-aos="zoom-in">
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-[0_18px_60px_-40px_rgba(15,23,42,0.35)] overflow-hidden">
                        <div class="relative h-56">
                            <img src="{{ asset('site-images/home-Slider_-_Studenten.jpg') }}" alt=""
                                 class="absolute inset-0 h-full w-full object-cover">
                        </div>
                        <div class="p-6">
                            <h2 class="text-lg font-semibold text-slate-900">Kontaktiere uns!</h2>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                                Egal, ob du Fragen, Vorschläge oder Wünsche hast – wir sind für dich da. Schreib uns einfach!
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2 text-xs">
                                <span class="inline-flex items-center rounded-full bg-blue-50 px-3 py-1 font-semibold text-blue-800 ring-1 ring-blue-100">
                                    <span class="mr-2 h-2 w-2 rounded-full bg-blue-500"></span>
                                    Schnell & unkompliziert
                                </span>
                                <span class="inline-flex items-center rounded-full bg-emerald-50 px-3 py-1 font-semibold text-emerald-800 ring-1 ring-emerald-100">
                                    <span class="mr-2 h-2 w-2 rounded-full bg-emerald-500"></span>
                                    Support-Team
                                </span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            {{-- Feature chips row --}}
            <div class="mt-10 grid grid-cols-1 sm:grid-cols-3 gap-4">
                <div class="rounded-2xl border border-slate-200 bg-white/70 backdrop-blur p-5 shadow-sm" data-aos="fade-up" data-aos-delay="50">
                    <div class="flex items-start gap-3">
                        <div class="h-11 w-11 rounded-2xl bg-blue-600 text-white flex items-center justify-center shadow-sm">
                            <i class="fas fa-headset"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">Support</p>
                            <p class="mt-1 text-sm text-slate-600">Direkt an unser Team</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/70 backdrop-blur p-5 shadow-sm" data-aos="fade-up" data-aos-delay="100">
                    <div class="flex items-start gap-3">
                        <div class="h-11 w-11 rounded-2xl bg-emerald-600 text-white flex items-center justify-center shadow-sm">
                            <i class="fas fa-paper-plane"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">Schnell senden</p>
                            <p class="mt-1 text-sm text-slate-600">Betreff & Nachricht reichen</p>
                        </div>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-200 bg-white/70 backdrop-blur p-5 shadow-sm" data-aos="fade-up" data-aos-delay="150">
                    <div class="flex items-start gap-3">
                        <div class="h-11 w-11 rounded-2xl bg-slate-900 text-white flex items-center justify-center shadow-sm">
                            <i class="fas fa-map-marker-alt"></i>
                        </div>
                        <div>
                            <p class="font-semibold text-slate-900">Standort</p>
                            <p class="mt-1 text-sm text-slate-600">Karte direkt eingebunden</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- MAIN --}}
    <main class="container mx-auto px-5 md:px-10 pb-14 ">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6">
            {{-- FORM (Primary) --}}
            <section class="lg:col-span-7" data-aos="fade-up">
                <div class="rounded-3xl p-[1px] bg-gradient-to-br from-blue-400 via-emerald-300 to-blue-200 shadow-[0_18px_60px_-40px_rgba(15,23,42,0.35)]">
                    <div class="rounded-3xl bg-white border border-white/60">
                        <div class="p-6 md:p-8">
                            <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                                <div>
                                    <h2 class="text-xl font-semibold text-slate-900">Nachricht senden</h2>
                                </div>
                            </div>

                            @if (session()->has('success'))
                                <div class="mt-6 rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">
                                    <div class="flex items-start gap-3">
                                        <span class="mt-0.5 inline-flex h-7 w-7 items-center justify-center rounded-full bg-emerald-600 text-white text-xs">✓</span>
                                        <div class="text-sm leading-relaxed">
                                            {{ session('success') }}
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="mt-6 grid grid-cols-1 gap-5">
                                <div>
                                    <x-label for="subject" value="Betreff" />
                                    <x-input
                                        wire:model.defer="subject"
                                        id="subject"
                                        class="block mt-2 w-full rounded-2xl"
                                        type="text"
                                        name="subject"
                                        required
                                        placeholder="Worum geht es?"
                                    />
                                    @error('subject')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div>
                                    <x-label for="message" value="Deine Nachricht" />
                                    <textarea
                                        wire:model.defer="message"
                                        id="message"
                                        name="message"
                                        rows="7"
                                        required
                                        class="block mt-2 w-full rounded-2xl border-slate-300 shadow-sm focus:ring-blue-600 focus:border-blue-600"
                                        placeholder="Schreibe deine Nachricht hier..."
                                    ></textarea>
                                    @error('message')
                                        <p class="mt-2 text-sm text-red-600">{{ $message }}</p>
                                    @enderror
                                </div>

                                <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between pt-1">
                                    <p class="text-xs text-slate-500 max-w-md">
                                        Mit dem Senden stimmst du zu, dass wir dich zur Bearbeitung kontaktieren.
                                    </p>

                                    <button
                                        wire:click="send"
                                        class="inline-flex items-center justify-center rounded-2xl bg-gradient-to-r from-blue-600 to-emerald-600 px-6 py-3 text-sm font-semibold text-white shadow-sm hover:brightness-110 focus:outline-none focus:ring-2 focus:ring-blue-200 active:scale-[0.99] transition"
                                    >
                                        Nachricht senden
                                        <i class="fas fa-paper-plane ml-2"></i>
                                    </button>
                                </div>
                            </div>

                            {{-- Social buttons (FontAwesome 5 Pro) --}}
                            <div class="mt-7 flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3">
                                <div class="text-sm font-semibold text-slate-700">Social</div>
                                <div class="flex items-center gap-2">
                                    <a href="https://www.facebook.com" target="_blank"
                                       class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 shadow-sm hover:shadow transition">
                                        <i class="fab fa-facebook-f text-blue-600"></i>
                                        Facebook
                                    </a>
                                    <a href="https://www.instagram.com" target="_blank"
                                       class="inline-flex items-center gap-2 rounded-xl bg-white px-4 py-2 text-sm font-semibold text-slate-700 ring-1 ring-slate-200 shadow-sm hover:shadow transition">
                                        <i class="fab fa-instagram text-pink-600"></i>
                                        Instagram
                                    </a>
                                </div>
                            </div>

                        </div>
                    </div>
                </div>
            </section>

            {{-- SIDE STACK (Map + Support) --}}
            <aside class="lg:col-span-5 space-y-6">
                {{-- MAP --}}
                <div class="rounded-3xl border border-slate-200 bg-white overflow-hidden shadow-sm hover:shadow-md transition"
                     data-aos="fade-left" data-aos-delay="100">
                    <div class="p-5 flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-base font-semibold text-slate-900">Standort</h3>
                            <p class="mt-1 text-sm text-slate-600">So findest du uns auf der Karte.</p>
                        </div>

                        <a href="https://www.google.com/maps" target="_blank"
                           class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-4 py-2 text-sm font-semibold text-slate-700 shadow-sm hover:shadow transition">
                            <i class="fas fa-map-marker-alt mr-2 text-slate-500"></i>
                            Öffnen
                        </a>
                    </div>

                    <div class="h-[340px]">
                        <iframe
                            src="https://www.google.com/maps/embed?pb=!1m10!1m8!1m3!1d75830.19174830109!2d9.9176227!3d53.5632388!3m2!1i1024!2i768!4f13.1!5e0!3m2!1sde!2sde!4v1747545555115!5m2!1sde!2sde"
                            data-cookieconsent="marketing"
                            width="100%" height="100%"
                            style="border:0;"
                            allowfullscreen=""
                            loading="lazy"
                            referrerpolicy="no-referrer-when-downgrade"
                        ></iframe>
                    </div>
                </div>

                {{-- SUPPORT --}}
                <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm"
                     data-aos="fade-left" data-aos-delay="150">
                    <div class="flex items-start gap-4">
                        <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 to-emerald-600 text-white flex items-center justify-center shadow-sm">
                            <i class="fas fa-headset"></i>
                        </div>

                        <div class="min-w-0">
                            <p class="text-sm font-semibold text-slate-900">Support</p>
                            <p class="mt-1 text-sm text-slate-600 truncate">info@cbw-weiterbildung.de</p>
                            <p class="mt-3 text-xs text-slate-500 leading-relaxed">
                                Schreib uns gerne direkt über das Formular. Bei dringenden Themen erreichst du uns auch per Mail.
                            </p>

                            <div class="mt-4 flex flex-wrap gap-2">
                                <a href="mailto:info@cbw-weiterbildung.de"
                                   class="inline-flex items-center rounded-2xl bg-primary px-4 py-2 text-sm font-semibold text-white hover:bg-primary-dark transition">
                                    <i class="fas fa-envelope mr-2"></i>
                                    Mail senden
                                </a>

                                <span class="inline-flex items-center rounded-2xl border border-slate-200 bg-slate-50 px-4 py-2 text-xs font-semibold text-slate-700">
                                    <i class="fas fa-clock mr-2 text-slate-500"></i>
                                    Werktags
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="text-xs text-slate-500 flex items-center justify-between" data-aos="fade-up" data-aos-delay="200">
                    <span>Support: info@cbw-weiterbildung.de</span>
                    <span>CBW Schulnetz</span>
                </div>
            </aside>
        </div>
    </main>
</div>
