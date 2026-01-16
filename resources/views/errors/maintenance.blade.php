<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Wartung - CBW Schulnetz</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        primary: '#2563eb',
                        secondary: '#0ea5e9',
                        accent: '#22c55e',
                    }
                }
            }
        };
    </script>
    <style>
        body {
            font-family: 'Inter', system-ui, -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        }
    </style>
</head>
<body class="min-h-screen bg-slate-50 text-slate-900 antialiased">
    <div class="relative min-h-screen overflow-hidden">
        <div class="absolute inset-0">
            <div class="absolute -left-20 -top-24 h-80 w-80 rounded-full bg-gradient-to-br from-blue-200 to-emerald-200 blur-3xl opacity-60"></div>
            <div class="absolute right-0 top-10 h-96 w-96 rounded-full bg-gradient-to-bl from-blue-300 via-emerald-200 to-white blur-3xl opacity-50"></div>
            <div class="absolute inset-x-10 bottom-0 h-32 bg-gradient-to-r from-blue-50 via-white to-emerald-50 blur-xl opacity-80"></div>
        </div>

        <main class="relative container mx-auto px-5 md:px-10 py-12">
            <div class="flex flex-col gap-8 lg:flex-row lg:items-start">
                <section class="lg:flex-1" aria-label="Wartungsstatus">
                    <div class="rounded-3xl p-[1px] bg-gradient-to-br from-blue-500 via-emerald-400 to-blue-200 shadow-[0_18px_60px_-40px_rgba(15,23,42,0.35)]">
                        <div class="rounded-3xl bg-white border border-white/60 h-full">
                            <div class="p-6 md:p-8 space-y-6">
                                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
                                    <div class="flex items-center gap-3">
                                        <div class="h-12 w-12 rounded-2xl bg-gradient-to-br from-blue-600 to-emerald-600 text-white flex items-center justify-center shadow-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M9.594 3.94c.09-.542.56-.94 1.11-.94h2.593c.55 0 1.02.398 1.11.94l.213 1.274c.063.374.313.686.659.87.347.184.76.227 1.136.115l1.257-.377a1.125 1.125 0 011.366.806l.65 2.598a1.125 1.125 0 01-.505 1.26l-1.091.655c-.333.2-.533.564-.523.954l.036 1.437c.01.417.237.801.597 1.008l1.193.686c.47.27.707.809.57 1.324l-.65 2.598a1.125 1.125 0 01-1.366.806l-1.257-.377c-.376-.112-.79-.069-1.136.115-.346.184-.596.496-.659.87l-.213 1.274c-.09.542-.56.94-1.11.94h-2.594c-.55 0-1.02-.398-1.11-.94l-.213-1.274c-.063-.374-.312-.686-.659-.87-.347-.184-.76-.227-1.136-.115l-1.256.377a1.125 1.125 0 01-1.367-.806l-.65-2.598a1.125 1.125 0 01.57-1.324l1.194-.686c.36-.207.587-.59.597-1.008l.036-1.437c.01-.39-.19-.754-.524-.954l-1.09-.655a1.125 1.125 0 01-.506-1.26l.65-2.598a1.125 1.125 0 011.366-.806l1.257.377c.376.112.79.069 1.136-.115.346-.184.596-.496.659-.87l.213-1.274z" />
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                            </svg>
                                        </div>
                                        <div>
                                            <p class="text-sm font-semibold text-blue-700">Geplante Wartung</p>
                                            <h1 class="text-3xl md:text-4xl font-semibold tracking-tight text-slate-900">Wir sind gleich wieder da</h1>
                                        </div>
                                    </div>
                                    <span class="inline-flex items-center rounded-full bg-blue-50 px-4 py-2 text-sm font-semibold text-blue-700 ring-1 ring-blue-100">
                                        Status: aktiv
                                    </span>
                                </div>

                                <p class="text-base text-slate-600 leading-relaxed">
                                    Wir optimieren gerade unsere Plattform, damit alles reibungsloser laeuft. In wenigen Minuten bist du wieder online.
                                    Vielen Dank fuer deine Geduld.
                                </p>

                                <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                                    <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm">
                                        <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v12m-6-6h12" />
                                            </svg>
                                        </div>
                                        <div class="text-sm">
                                            <p class="font-semibold text-slate-900">Neue Funktionen</p>
                                            <p class="text-slate-600">Wir rollen Verbesserungen aus.</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm">
                                        <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-emerald-600 text-white shadow-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5" />
                                            </svg>
                                        </div>
                                        <div class="text-sm">
                                            <p class="font-semibold text-slate-900">Stabilitaet</p>
                                            <p class="text-slate-600">Wir sichern Daten & Performance.</p>
                                        </div>
                                    </div>

                                    <div class="flex items-start gap-3 rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 shadow-sm">
                                        <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-slate-900 text-white shadow-sm">
                                            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12l-7.5 7.5M10.5 4.5L3 12l7.5 7.5" />
                                            </svg>
                                        </div>
                                        <div class="text-sm">
                                            <p class="font-semibold text-slate-900">Kurze Auszeit</p>
                                            <p class="text-slate-600">Wir halten die Downtime minimal.</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="rounded-2xl border border-slate-200 bg-slate-50 px-5 py-4 shadow-sm">
                                    <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                                        <div>
                                            <p class="text-sm font-semibold text-slate-900">Voraussichtlich wieder online in</p>
                                            <p class="text-3xl font-semibold text-blue-700 mt-1" id="countdown" data-timestamp="{{ isset($lastUpdated) ? \Carbon\Carbon::parse($lastUpdated)->timestamp : \Carbon\Carbon::now()->timestamp }}">
                                                wird berechnet ...
                                            </p>
                                        </div>
                                        <div class="text-sm text-slate-600">
                                            <p class="font-semibold text-slate-900">Letzte Aktualisierung</p>
                                            <p>{{ isset($lastUpdated) ? \Carbon\Carbon::parse($lastUpdated)->setTimezone('Europe/Berlin')->format('d.m.Y H:i') : \Carbon\Carbon::now()->setTimezone('Europe/Berlin')->format('d.m.Y H:i') }} Uhr</p>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex flex-wrap items-center gap-3">
                                    <a href="/" class="inline-flex items-center rounded-2xl bg-gradient-to-r from-blue-600 to-emerald-600 px-5 py-3 text-sm font-semibold text-white shadow-sm hover:brightness-110 transition">
                                        Zur Startseite
                                        <svg xmlns="http://www.w3.org/2000/svg" class="ml-2 h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7" />
                                        </svg>
                                    </a>
                                    <a href="mailto:info@cbw-weiterbildung.de" class="inline-flex items-center rounded-2xl border border-slate-200 bg-white px-5 py-3 text-sm font-semibold text-slate-700 shadow-sm hover:shadow transition">
                                        <svg xmlns="http://www.w3.org/2000/svg" class="mr-2 h-4 w-4 text-blue-600" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                                            <path stroke-linecap="round" stroke-linejoin="round" d="M21.75 6.75v10.5a1.5 1.5 0 01-1.5 1.5h-16.5a1.5 1.5 0 01-1.5-1.5V6.75m19.5 0a1.5 1.5 0 00-1.5-1.5h-16.5a1.5 1.5 0 00-1.5 1.5m19.5 0v.243a1.5 1.5 0 01-.684 1.266l-8.25 5.156a1.5 1.5 0 01-1.632 0L2.934 8.259A1.5 1.5 0 012.25 6.993V6.75" />
                                        </svg>
                                        Support kontaktieren
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <aside class="lg:w-[420px] space-y-4" aria-label="Informationen">
                    <div class="rounded-3xl border border-slate-200 bg-white shadow-[0_18px_60px_-40px_rgba(15,23,42,0.35)] overflow-hidden">
                        <div class="relative h-52">
                            <img src="{{ asset('site-images/home-Slider_-_Studenten.jpg') }}" alt="Campus Illustration" class="absolute inset-0 h-full w-full object-cover">
                            <div class="absolute inset-0 bg-gradient-to-t from-slate-900/60 to-transparent"></div>
                            <div class="absolute bottom-4 left-4 text-white">
                                <p class="text-xs uppercase tracking-[0.12em] text-white/80">CBW Schulnetz</p>
                                <p class="text-lg font-semibold">Deine Lernplattform</p>
                            </div>
                        </div>
                        <div class="p-6">
                            <div class="flex items-center gap-2 text-sm font-semibold text-emerald-700">
                                <span class="inline-block h-2 w-2 rounded-full bg-emerald-500 animate-pulse"></span>
                                Wartung laeuft stabil
                            </div>
                            <p class="mt-2 text-sm text-slate-600 leading-relaxed">
                                Wir pruefen gerade alle Systeme und bringen Updates live. Deine Daten sind sicher und werden nicht veraendert.
                            </p>
                        </div>
                    </div>

                    <div class="rounded-3xl border border-slate-200 bg-white p-6 shadow-sm space-y-4">
                        <div class="flex items-start gap-3">
                            <div class="h-10 w-10 flex items-center justify-center rounded-xl bg-blue-600 text-white shadow-sm">
                                <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm-.75-12a.75.75 0 00-1.5 0v1.25a.75.75 0 001.5 0V6zm.75 3.5a.75.75 0 00-.75.75v4a.75.75 0 001.5 0v-4A.75.75 0 0010 9.5z" clip-rule="evenodd" />
                                </svg>
                            </div>
                            <div>
                                <p class="text-base font-semibold text-slate-900">Was passiert gerade?</p>
                                <p class="mt-1 text-sm text-slate-600">Das machen wir in dieser Wartungsrunde.</p>
                            </div>
                        </div>
                        <div class="space-y-3">
                            <div class="flex items-start gap-3">
                                <span class="mt-1 h-2 w-2 rounded-full bg-blue-600"></span>
                                <p class="text-sm text-slate-700">Updates und Sicherheitspatches einspielen.</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="mt-1 h-2 w-2 rounded-full bg-emerald-600"></span>
                                <p class="text-sm text-slate-700">Kurzer Systemcheck und Monitoring.</p>
                            </div>
                            <div class="flex items-start gap-3">
                                <span class="mt-1 h-2 w-2 rounded-full bg-slate-900"></span>
                                <p class="text-sm text-slate-700">User-Features vorbereiten und testen.</p>
                            </div>
                        </div>
                        <div class="rounded-2xl border border-dashed border-slate-200 bg-slate-50 px-4 py-3 text-sm text-slate-600">
                            Tipp: Bei dringenden Themen erreichst du uns per Mail an <a href="mailto:info@cbw-weiterbildung.de" class="text-blue-700 font-semibold">info@cbw-weiterbildung.de</a>.
                        </div>
                    </div>

                    <div class="text-xs text-slate-500 flex items-center justify-between">
                        <span>CBW Schulnetz</span>
                        <span>Wir sind gleich wieder online</span>
                    </div>
                </aside>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const countdownElement = document.getElementById('countdown');
            if (!countdownElement) return;

            const lastUpdated = Number(countdownElement.dataset.timestamp || 0);
            const windowSeconds = 600; // 10 Minuten

            const formatTime = (seconds) => {
                if (seconds <= 0) return 'gleich!';
                if (seconds >= 60) {
                    const minutes = Math.floor(seconds / 60);
                    const remaining = seconds % 60;
                    return `${minutes} Min. ${remaining} Sek.`;
                }
                return `${seconds} Sek.`;
            };

            const updateCountdown = () => {
                const now = Math.floor(Date.now() / 1000);
                const remaining = Math.max(windowSeconds - (now - lastUpdated), 0);
                countdownElement.textContent = formatTime(remaining);
            };

            updateCountdown();
            setInterval(updateCountdown, 1000);
        });
    </script>
</body>
</html>
