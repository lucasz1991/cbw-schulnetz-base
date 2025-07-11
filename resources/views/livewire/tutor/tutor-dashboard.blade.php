<div class="p-6 ">
    <div class="container mx-auto bg-white shadow-lg rounded-lg">
        
        <!-- Begrüßung -->
        <div class="px-6 py-4 border-b border-gray-200">
            <h1 class="text-2xl font-bold text-gray-800">Willkommen, Max Mustermann!</h1>
            <p class="text-gray-600 mt-1">Dein persönliches Dashboard mit allen Kursen und Teilnehmern.</p>
        </div>

        <!-- Suche (nicht funktionsfähig, nur Demo) -->
        <div class="px-6 py-4">
            <input
                type="text"
                placeholder="🔍 Suche nach Kursen..."
                class="w-full px-4 py-2 border border-gray-300 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary-500"
            />
        </div>

        <!-- Kursliste (statisch) -->
        <div class="px-6 pb-6">
            <h2 class="text-xl font-semibold text-gray-700 mb-3">Meine Kurse</h2>

            <div class="space-y-4">

                <!-- Kurs 1 -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-bold text-purple-700">Mathematik für Anfänger</h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Einführung in die Grundbegriffe der Algebra und Geometrie. Für Schüler ab Klasse 7 geeignet.
                            </p>
                            <p class="text-xs text-gray-500 mt-2">Beginn: 15.08.2025 10:00 Uhr</p>
                        </div>
                        <div class="text-sm text-gray-400">
                            <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full">#101</span>
                        </div>
                    </div>
                </div>

                <!-- Kurs 2 -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-bold text-purple-700">Englisch Konversation B1</h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Wöchentliche Konversationsrunden zur Verbesserung der Sprachpraxis im Alltag.
                            </p>
                            <p class="text-xs text-gray-500 mt-2">Beginn: 22.08.2025 17:30 Uhr</p>
                        </div>
                        <div class="text-sm text-gray-400">
                            <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full">#102</span>
                        </div>
                    </div>
                </div>

                <!-- Kurs 3 -->
                <div class="bg-gray-50 border border-gray-200 rounded-lg p-4 shadow-sm hover:shadow-md transition">
                    <div class="flex justify-between items-start">
                        <div>
                            <h3 class="text-lg font-bold text-purple-700">Physik Intensivkurs</h3>
                            <p class="text-sm text-gray-600 mt-1">
                                Vorbereitung auf das Abitur mit Fokus auf Mechanik und Elektrodynamik.
                            </p>
                            <p class="text-xs text-gray-500 mt-2">Beginn: 01.09.2025 14:00 Uhr</p>
                        </div>
                        <div class="text-sm text-gray-400">
                            <span class="bg-purple-100 text-purple-700 px-3 py-1 rounded-full">#103</span>
                        </div>
                    </div>
                </div>

                <!-- Kein Kurs gefunden (wird in der Live-Version durch empty-Zweig ersetzt) -->
                {{-- <div class="text-gray-500 italic">Keine Kurse gefunden.</div> --}}

            </div>
        </div>
    </div>
</div>
