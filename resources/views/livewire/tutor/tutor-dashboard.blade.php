<div class="container mx-auto px-4 py-6">
    <!-- Begrüßung als Card -->
    <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
        <h1 class="text-2xl font-bold text-gray-800">Willkommen, Max Mustermann!</h1>
        <p class="text-gray-600 mt-1">Dein persönliches Dashboard mit allen Kursen und Teilnehmern.</p>
    </div>
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mt-6">

        <!-- Linke Spalte: Begrüßung + Kurse -->
        <div class="lg:col-span-2 space-y-6">


            <!-- Kursliste als Card -->
            <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
                <h2 class="text-xl font-semibold text-gray-700 mb-4">Meine Kurse</h2>
                <div class="space-y-4">
                    <!-- Kurs 1 -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-bold text-blue-700">Mathematik für Anfänger</h3>
                                <p class="text-sm text-gray-600 mt-1">Einführung in die Grundbegriffe der Algebra und Geometrie. Für Schüler ab Klasse 7 geeignet.</p>
                                <p class="text-xs text-gray-500 mt-2">Beginn: 15.08.2025 10:00 Uhr</p>
                            </div>
                            <div class="text-sm text-gray-400">
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full">#101</span>
                            </div>
                        </div>
                    </div>

                    <!-- Kurs 2 -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-bold text-blue-700">Englisch Konversation B1</h3>
                                <p class="text-sm text-gray-600 mt-1">Wöchentliche Konversationsrunden zur Verbesserung der Sprachpraxis im Alltag.</p>
                                <p class="text-xs text-gray-500 mt-2">Beginn: 22.08.2025 17:30 Uhr</p>
                            </div>
                            <div class="text-sm text-gray-400">
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full">#102</span>
                            </div>
                        </div>
                    </div>

                    <!-- Kurs 3 -->
                    <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition">
                        <div class="flex justify-between items-start">
                            <div>
                                <h3 class="text-lg font-bold text-blue-700">Physik Intensivkurs</h3>
                                <p class="text-sm text-gray-600 mt-1">Vorbereitung auf das Abitur mit Fokus auf Mechanik und Elektrodynamik.</p>
                                <p class="text-xs text-gray-500 mt-2">Beginn: 01.09.2025 14:00 Uhr</p>
                            </div>
                            <div class="text-sm text-gray-400">
                                <span class="bg-blue-100 text-blue-700 px-3 py-1 rounded-full">#103</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <a href="" class="text-blue-600 hover:underline">Alle Kurse ansehen →</a>
                </div>
            </div>
        </div>

        <!-- Rechte Spalte: Zusatzinfos als einzelne Cards -->
        <div class="space-y-6">
            <!-- Nachrichten -->
            <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-blue-700 mb-2">📩 Nachrichten</h3>
                <!-- Nachrichtenliste -->

                @php
                    $receivedMessages = [];
                @endphp
                @forelse($receivedMessages as $message)
                    <div 
                        @click="modalOpen = true; open = false; selectedMessage = { subject: '{{ $message->subject }}', body: '{!! addslashes($message->message) !!}', createdAt: '{{ $message->created_at->diffForHumans() }}' }; $wire.setMessageStatus({{ $message->id }}); " 
                        class="flex items-center p-4 hover:bg-slate-50 cursor-pointer @if($message->status == 1) bg-blue-200 @endif">
                        <div class="block h-10 w-10 size-4 flex-none rounded-full">
                            <x-application-logo class="w-10" />
                        </div>
                        <div class="ml-4 flex-auto">    
                            <div class="font-medium">{{ $message->subject }}</div>
                            <div class="mt-1 text-slate-700">
                                {{ Str::limit(strip_tags($message->message), 40) }}
                            </div>
                        </div>
                    </div>
                @empty
                    <div class="p-4 text-center text-slate-700">
                        Keine  Nachrichten
                    </div>
                @endforelse
                    <!-- "Alle ansehen"-Button -->
                    <div class="mt-4">
                        <a href="{{ route('messages') }}" 
                            class="pointer-events-auto rounded-md px-4 py-2 text-center font-medium ring-1 shadow-xs ring-slate-700/10 hover:bg-slate-50 block">
                            Alle Nachrichten ansehen
                        </a>
                    </div>
            </div>

            <!-- News -->
            <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-blue-700 mb-2">📰 News</h3>
                <ul class="text-sm text-gray-700 space-y-1">
                    <li><strong>Neue Kursplattform</strong> startet im August</li>
                    <li><strong>Update:</strong> Uploads jetzt möglich</li>
                    <li><strong>Admin:</strong> Systemwartung am 20.07.</li>
                </ul>
                <a href="#" class="block text-sm text-blue-600 hover:underline mt-3">Weitere News →</a>
            </div>

            <!-- Termine -->
            <div class="bg-white shadow-md rounded-lg p-6 border border-gray-200">
                <h3 class="text-lg font-bold text-green-700 mb-2">📅 Nächste Termine</h3>
                <ul class="text-sm text-gray-700 space-y-1">
                    <li>🗓️ <strong>15.07.:</strong> Mathe – Trigonometrie</li>
                    <li>🗓️ <strong>17.07.:</strong> Englisch B1 – Speaking</li>
                    <li>🗓️ <strong>18.07.:</strong> Physik – Prüfungsvorbereitung</li>
                </ul>
                <a href="#" class="block text-sm text-green-600 hover:underline mt-3">Zum Kalender →</a>
            </div>
        </div>

    </div>
</div>
