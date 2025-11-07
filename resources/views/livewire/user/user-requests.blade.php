<div class=" mt-4" wire:loading.class="opacity-50 pointer-events-none">
    <livewire:user.absences  lazy />
    <livewire:user.makeup-exam-registration  lazy />
    <livewire:user.external-makeup-registration lazy />
    <livewire:user.request-detail-modal lazy />


    <div class="mb-12">
    <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
    {{-- Fehlzeit melden --}}
    <div
        role="button"
        tabindex="0"
        class="group relative bg-white border rounded-xl p-4 hover:shadow-lg hover:border-blue-200 transition-all cursor-pointer focus:outline-none focus:ring-2 focus:ring-blue-400"

        onclick="Livewire.dispatch('open-absence-form')"
        aria-label="Fehlzeit melden"
    >
        <div class="flex items-start gap-3">
        {{-- Icon --}}
        <div class="shrink-0 mt-0.5 rounded-lg border bg-blue-50 text-blue-600 border-blue-100 p-2 group-hover:bg-blue-100">
            {{-- Calendar/Absence Icon (Heroicons) --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M8 7V3m8 4V3M4 11h16M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z" />
            </svg>
        </div>

        <div class="flex-1">
            <div class="font-semibold text-gray-900">Fehlzeit melden</div>
            <p class="text-sm text-gray-600 mt-0.5">
            Entschuldigung einreichen oder Teil-/Ganztags-Abwesenheit dokumentieren.
            </p>

            <div class="mt-3 flex items-center gap-2">
            <button
                type="button"

                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium border border-blue-200 text-blue-700 bg-blue-50 group-hover:bg-blue-100"
            >
                Jetzt melden
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 -mr-0.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 11-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
            </div>
        </div>
        </div>
    </div>

    {{-- Nachprüfung beantragen (intern) --}}
    <div
        role="button"
        tabindex="0"
        class="group relative bg-white border rounded-xl p-4 hover:shadow-lg hover:border-emerald-200 transition-all cursor-pointer focus:outline-none focus:ring-2 focus:ring-emerald-400"
        onclick="Livewire.dispatch('open-makeup-form')"
        aria-label="Nachprüfung beantragen"
    >
        <div class="flex items-start gap-3">
        {{-- Icon --}}
        <div class="shrink-0 mt-0.5 rounded-lg border bg-emerald-50 text-emerald-600 border-emerald-100 p-2 group-hover:bg-emerald-100">
            {{-- Clipboard/Check Icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M9 12l2 2 4-4M9 5h6a2 2 0 012 2v12a2 2 0 01-2 2H9m0-16a2 2 0 00-2 2v12a2 2 0 002 2m0-16h6" />
            </svg>
        </div>

        <div class="flex-1">
            <div class="font-semibold text-gray-900">Nachprüfung beantragen</div>
            <p class="text-sm text-gray-600 mt-0.5">
            Interne Nach-/Wiederholungsprüfung inkl. Termin & Begründung wählen.
            </p>

            <div class="mt-3 flex items-center gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium border border-emerald-200 text-emerald-700 bg-emerald-50 group-hover:bg-emerald-100"
            >
                Antrag starten
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 -mr-0.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 11-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
            </div>
        </div>
        </div>
    </div>

    {{-- Externe Nachprüfung beantragen --}}
    <div
        role="button"
        tabindex="0"
        class="group relative bg-white border rounded-xl p-4 hover:shadow-lg hover:border-purple-200 transition-all cursor-pointer focus:outline-none focus:ring-2 focus:ring-purple-400"
        onclick="Livewire.dispatch('open-external-makeup-form')"
        aria-label="Externe Nachprüfung beantragen"
    >
        <div class="flex items-start gap-3">
        {{-- Icon --}}
        <div class="shrink-0 mt-0.5 rounded-lg border bg-purple-50 text-purple-600 border-purple-100 p-2 group-hover:bg-purple-100">
            {{-- Globe/Certificate Icon --}}
            <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M12 3a9 9 0 100 18 9 9 0 000-18zm0 0c2.5 2.5 2.5 6.5 0 9-2.5 2.5-6.5 2.5-9 0m18 0c-2.5 2.5-6.5 2.5-9 0" />
            </svg>
        </div>

        <div class="flex-1">
            <div class="font-semibold text-gray-900">Externe Nachprüfung</div>
            <p class="text-sm text-gray-600 mt-0.5">
            Zertifizierung auswählen (z. B. SAP/ICDL) und Wunschtermin buchen.
            </p>

            <div class="mt-3 flex items-center gap-2">
            <button
                type="button"
                class="inline-flex items-center gap-2 px-3 py-1.5 rounded-md text-sm font-medium border border-purple-200 text-purple-700 bg-purple-50 group-hover:bg-purple-100"
            >
                Zertifizierung wählen
                <svg xmlns="http://www.w3.org/2000/svg" class="h-4 w-4 -mr-0.5" viewBox="0 0 20 20" fill="currentColor">
                <path fill-rule="evenodd" d="M10.293 3.293a1 1 0 011.414 0l6 6a1 1 0 010 1.414l-6 6a1 1 0 11-1.414-1.414L14.586 11H3a1 1 0 110-2h11.586l-4.293-4.293a1 1 0 010-1.414z" clip-rule="evenodd"/>
                </svg>
            </button>
            </div>
        </div>
        </div>
    </div>
    </div>

    </div>


    <div class="bg-white mb-4 p-4 pb-1 rounded-lg border shadow-sm">        
        {{-- Toolbar --}}
        <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-4">
            <h2 class="text-lg font-semibold">Meine Anträge</h2>
    
            <div class="flex items-center gap-2">
                <x-tables.search-field 
                    resultsCount="{{ $requests->count() }}"
                    wire:model.live="search"
                />
    
    
                <select class="px-2 py-0.5 h-[30px] rounded border border-gray-300 pr-8 opacity-70 hover:opacity-100 transition"
                        wire:model.live="filterType" title="Typ">
                    <option value="all">Alle Typen</option>
                    <option value="makeup">Nachprüfung (intern)</option>
                    <option value="external_makeup">Nachprüfung (extern)</option>
                    <option value="absence">Fehlzeit</option>
                    <option value="general">Allgemein</option>
                </select>

                <select class="px-2 py-0.5 h-[30px] rounded border border-gray-300 pr-8 opacity-70 hover:opacity-100 transition"
                        wire:model.live="filterStatus" title="Status">
                    <option value="all">Alle Status</option>
                    <option value="pending">Eingereicht</option>
                    <option value="in_review">In Prüfung</option>
                    <option value="approved">Genehmigt</option>
                    <option value="rejected">Abgelehnt</option>
                    <option value="canceled">Storniert</option>
                </select>
    
            </div>
        </div>
    
        {{-- Tabelle --}}
        <div class="">
            <x-tables.table
                :columns="[
                    ['label'=>'Typ','key'=>'type','width'=>'30%','sortable'=>false,'hideOn'=>'none'],
                    ['label'=>'Zeitraum','key'=>'date_range','width'=>'40%','sortable'=>false,'hideOn'=>'lg'],
                    ['label'=>'Status','key'=>'status','width'=>'30%','sortable'=>false,'hideOn'=>'md'],
                ]"
                :items="$requests"
                row-view="components.tables.rows.user-requests.row"
                actions-view="components.tables.rows.user-requests.actions"
            />
    
            <div class="p-3">
                {{ $requests->links() }}
            </div>
        </div>
    </div>
</div>
