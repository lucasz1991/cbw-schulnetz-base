<div class="max-w-xl mx-auto px-4 py-10 text-center" 
    x-data="{ claimHash: $persist(null) }"
    x-init="
        @if (!$claimRating->user_id)
            claimHash = '{{ $claimRating->verification_hash }}'
        @endif
    ">
    <h1 class="text-2xl font-bold text-green-700 mb-4">Vielen Dank für deine Bewertung!</h1>

    <p class="text-gray-700 mb-6">
        Deine Bewertung wurde erfolgreich gespeichert.
        @if (!$claimRating->user_id)
            Du hast die Bewertung anonym abgegeben.

            <br><br>
            <span class="block text-sm text-red-700 font-semibold">
                Damit deine Bewertung verifiziert und anonymisiert veröffentlicht sowie in die Auswertung einbezogen werden kann, musst du dich registrieren oder einloggen.
                Ohne eine Registrierung kann deine Bewertung leider nicht berücksichtigt werden.
            </span>

            <div class="mt-4 flex flex-col sm:flex-row gap-3 justify-center">
                <a href="{{ route('register') }}" class="inline-block px-4 py-2 bg-blue-600 text-white rounded hover:bg-blue-700">
                    Registrierung
                </a>
                <a href="{{ route('login') }}" class="inline-block px-4 py-2 bg-gray-600 text-white rounded hover:bg-gray-700">
                    Login
                </a>
            </div>

            <p class="text-sm text-gray-500 mt-4">
                Wenn du keine Verifizierung durchführst, bleibt deine Bewertung anonym und wird nicht in die Analyse aufgenommen.
            </p>
        @endif
    </p>
</div>
