<x-dialog-modal wire:model="showModal" maxWidth="2xl">
    <x-slot name="title">
        Fehlzeit entschuldigen
    </x-slot>

    <x-slot name="content">
        {{-- Dein bisheriger Formular-Block 1:1 übernommen, nur ohne <h2> und äußeres .bg --}}
        <form
            x-data="{
                fehltag: @entangle('fehltag').live,
                abw_grund: @entangle('abw_grund').live,
                showGrundBox: false,
                showDeleteUpload: false
            }"
            class="space-y-6"
            {{-- wire:submit.prevent="save"  <- später aktivieren, wenn save() bereit --}}
        >

            {{-- Versteckte Felder ggf. via Backend befüllen; hier vorerst belassen --}}
            <input type="hidden" name="tn_name" value="Müstermann, Mäx">
            <input type="hidden" name="tn_nummer" value="0000007">
            <input type="hidden" name="institut" value="Köln">
            <input type="hidden" name="email" value="mm@muster.com">
            <input type="hidden" name="send_date" value="28.05.2025 - 06:02">

            <div class="grid md:grid-cols-3 gap-4">
                <div class="md:col-span-2">
                    <label class="font-medium">Name:</label>
                    <p class="text-gray-700 font-semibold">Müstermann, Mäx</p>
                </div>
                <div>
                    <label for="klasse" class="block text-sm font-medium">Klasse</label>
                    <input id="klasse" name="klasse" type="text" required maxlength="8" placeholder="Klasse"
                           class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           wire:model.defer="klasse">
                </div>
            </div>

            <div class="grid md:grid-cols-3 gap-4 items-end">
                <div>
                    <label class="inline-flex items-center space-x-2">
                        <input type="checkbox"
                               x-model="fehltag"
                               class="text-blue-600 border-gray-300 rounded">
                        <span>Ganztägig gefehlt</span>
                    </label>
                </div>
                <div class="md:col-span-2">
                    <label for="fehlDatum" class="block text-sm font-medium">Datum</label>
                    <input id="fehlDatum" name="fehlDatum" type="date" required
                           class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
                           wire:model.defer="fehlDatum">
                </div>
            </div>

            <div class="grid md:grid-cols-2 gap-4">
                <div>
                    <label for="fehlUhrGek" class="block text-sm font-medium">Später gekommen (Uhrzeit)</label>
                    <input id="fehlUhrGek" name="fehlUhrGek" type="time" min="08:00" max="23:00"
                           class="mt-1 block w-full border-gray-300 rounded shadow-sm"
                           wire:model.defer="fehlUhrGek">
                </div>
                <div>
                    <label for="fehlUhrGeg" class="block text-sm font-medium">Früher gegangen (Uhrzeit)</label>
                    <input id="fehlUhrGeg" name="fehlUhrGeg" type="time" min="08:00" max="23:00"
                           class="mt-1 block w-full border-gray-300 rounded shadow-sm"
                           wire:model.defer="fehlUhrGeg">
                </div>
            </div>

            <div class="space-y-2">
                <label class="font-medium block">Grund der Fehlzeit</label>
                <div class="flex items-center space-x-4">
                    <label class="inline-flex items-center">
                        <input type="radio" name="abw_grund" value="abw_wichtig"
                               @change="showGrundBox = true"
                               class="text-blue-600 border-gray-300"
                               :checked="$wire.abw_grund === 'abw_wichtig'"
                               @click="$wire.abw_grund = 'abw_wichtig'">
                        <span class="ml-2">Mit wichtigem Grund</span>
                    </label>
                    <label class="inline-flex items-center">
                        <input type="radio" name="abw_grund" value="abw_unwichtig"
                               @change="showGrundBox = false"
                               class="text-blue-600 border-gray-300"
                               :checked="$wire.abw_grund === 'abw_unwichtig'"
                               @click="$wire.abw_grund = 'abw_unwichtig'">
                        <span class="ml-2">Ohne wichtigen Grund</span>
                    </label>
                </div>

                <div x-show="showGrundBox" class="mt-2">
                    <label for="grund_item" class="block text-sm font-medium">Grund auswählen</label>
                    <select id="grund_item"
                            class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
                            wire:model.defer="grund_item">
                        <option value="">?</option>
                        <option>Wohnungswechsel</option>
                        <option>Krankheit</option>
                        <option>Eheschließung des Teilnehmers / eines Kindes</option>
                        <option>Ehejubiläum des Teilnehmers, der Eltern oder Schwiegereltern</option>
                        <option>Schwere Erkrankungen des Ehegatten oder eines Kindes</option>
                        <option>Niederkunft der Ehefrau</option>
                        <option>Tod des Ehegatten, eines Kindes, eines Eltern- oder Schwiegerelternteils</option>
                        <option>Wahrnehmung amtlicher Termine</option>
                        <option>Ausübung öffentlicher Ehrenämter</option>
                        <option>Religiöse Feste</option>
                        <option>Katastrophenschutz-Einsätze</option>
                    </select>
                </div>
            </div>

            <div>
                <label for="begruendung" class="block text-sm font-medium">Sonstige Begründung</label>
                <textarea id="begruendung" maxlength="400" placeholder="max. 400 Zeichen"
                          class="mt-1 block w-full border-gray-300 rounded shadow-sm focus:ring-blue-500 focus:border-blue-500"
                          wire:model.defer="begruendung"></textarea>
            </div>

            {{-- Upload-Bereich: belassen; später zu Livewire Upload migrieren --}}
            <div class="border-t pt-4">
                <label class="block text-sm font-medium mb-2">Anlage (jpg, png, gif, pdf)</label>
                <div class="flex items-center space-x-4">
                    <button type="button" class="upload-btn bg-gray-100 border px-4 py-2 rounded text-sm">Anlage hinzufügen</button>
                    <span class="text-gray-500">Keine Anlage hinzugefügt</span>
                    <span x-show="showDeleteUpload">
                        <a href="#" class="text-red-600 text-sm">[ löschen ]</a>
                    </span>
                </div>
            </div>
        </form>
    </x-slot>

    <x-slot name="footer">
        <x-secondary-button wire:click="close">Schließen</x-secondary-button>
        <x-button class="ml-2" wire:click="save">Speichern</x-button>
    </x-slot>
</x-dialog-modal>
