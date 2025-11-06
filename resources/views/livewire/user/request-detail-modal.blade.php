<x-dialog-modal wire:model="showModal" maxWidth="3xl">
    <x-slot name="title">
        Antrag – Details
    </x-slot>

    <x-slot name="content">
        @if(!$request)
            <div class="text-sm text-gray-500">Kein Antrag geladen.</div>
        @else
            @php
                $typeLabel = [
                    'makeup'          => 'Nachprüfung (intern)',
                    'external_makeup' => 'Nachprüfung (extern)',
                    'absence'         => 'Fehlzeit',
                    'general'         => 'Allgemein',
                ][$request->type] ?? ucfirst($request->type);

                $statusLabel = [
                    'pending'   => 'Eingereicht',
                    'in_review' => 'In Prüfung',
                    'approved'  => 'Genehmigt',
                    'rejected'  => 'Abgelehnt',
                    'canceled'  => 'Storniert',
                ][$request->status] ?? ucfirst($request->status);
            @endphp

            {{-- Kopf: Typ + Status --}}
            <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
                <div class="text-lg font-semibold">
                    {{ $typeLabel }}
                    @if($request->title)
                        <span class="text-gray-500 font-normal">— {{ $request->title }}</span>
                    @endif
                </div>

                <span class="inline-flex items-center rounded-full px-2 py-0.5 text-xs
                    @class([
                        'bg-yellow-100 text-yellow-800' => $request->status === 'pending',
                        'bg-blue-100 text-blue-800'     => $request->status === 'in_review',
                        'bg-green-100 text-green-700'   => $request->status === 'approved',
                        'bg-red-100 text-red-700'       => $request->status === 'rejected',
                        'bg-gray-100 text-gray-700'     => $request->status === 'canceled',
                    ])
                ">{{ $statusLabel }}</span>
            </div>

            {{-- Basisdaten --}}
            <div class="grid md:grid-cols-2 gap-4">
                <x-ui.detail-item label="Klasse" :value="$request->class_code ?: '—'"/>
                <x-ui.detail-item label="Teilnehmer-Nr." :value="$request->participant_no ?: '—'"/>

                <x-ui.detail-item label="Baustein/Modul" :value="$request->module_code ?: '—'"/>
                <x-ui.detail-item label="Dozent" :value="$request->instructor_name ?: '—'"/>

                @if($request->type === 'absence')
                    <x-ui.detail-item label="Datum" :value="$request->date_from?->format('d.m.Y') ?: '—'"/>
                    <x-ui.detail-item label="Ganztägig" :value="$request->full_day ? 'Ja' : 'Nein'"/>
                    @if(!$request->full_day)
                        <x-ui.detail-item label="Später ab" :value="$request->time_arrived_late ? substr($request->time_arrived_late,0,5) : '—'"/>
                        <x-ui.detail-item label="Früher bis" :value="$request->time_left_early ? substr($request->time_left_early,0,5) : '—'"/>
                    @endif
                    <x-ui.detail-item label="Grund" :value="$request->reason ? str_replace('_',' ', $request->reason) : '—'"/>
                    <x-ui.detail-item label="Grund (Detail)" :value="$request->reason_item ?: '—'"/>
                @else
                    <x-ui.detail-item label="Geplanter Termin" :value="$request->scheduled_at ? $request->scheduled_at->timezone(config('app.timezone'))->format('d.m.Y H:i') : '—'"/>
                    <x-ui.detail-item label="Ursprüngliche Prüfung" :value="$request->original_exam_date?->format('d.m.Y') ?: '—'"/>
                    <x-ui.detail-item label="Begründung" :value="$request->reason ? str_replace('_',' ', $request->reason) : '—'"/>
                    <x-ui.detail-item label="Attest" :value="is_null($request->with_attest) ? '—' : ($request->with_attest ? 'Ja' : 'Nein')"/>
                    <x-ui.detail-item label="Gebühr" :value="$request->fee_formatted ?: '—'"/>

                    {{-- Extern --}}
                    <x-ui.detail-item label="Zertifizierung" :value="$request->certification_label ?: ($request->certification_key ?: '—')"/>
                    <x-ui.detail-item label="Durchführung" :value="$request->exam_modality ?: '—'"/>
                @endif

                <x-ui.detail-item label="Eingereicht am" :value="$request->submitted_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?: $request->created_at->timezone(config('app.timezone'))->format('d.m.Y H:i')"/>
                <x-ui.detail-item label="Entschieden am" :value="$request->decided_at?->timezone(config('app.timezone'))->format('d.m.Y H:i') ?: '—'"/>
            </div>

            {{-- Nachricht / Kommentar --}}
            @if($request->message)
                <div class="mt-4">
                    <div class="text-xs font-semibold text-gray-500 mb-1">Nachricht</div>
                    <div class="rounded border bg-gray-50 p-3 text-sm whitespace-pre-line">{{ $request->message }}</div>
                </div>
            @endif

            {{-- Anhänge --}}
            <div class="mt-6">
                <div class="text-xs font-semibold text-gray-500 mb-2">Anhänge</div>
                @if($request->files && $request->files->count())
                    <ul class="space-y-2">
                        @foreach($request->files as $f)
                            <li class="flex items-center justify-between gap-3 border rounded px-3 py-2">
                                <div class="flex items-center gap-3 min-w-0">
                                    <img src="{{ $f->icon_or_thumbnail }}" class="h-8 w-8 rounded object-cover border" alt="">
                                    <div class="min-w-0">
                                        <div class="truncate text-sm font-medium">{{ $f->name_with_extension }}</div>
                                        <div class="text-xs text-gray-500">{{ $f->getMimeTypeForHumans() }} · {{ $f->size_formatted }}</div>
                                    </div>
                                </div>
                                <div class="shrink-0">
                                    {{-- temporäre öffentliche URL (10 Min) --}}
                                    <a href="{{ $f->getEphemeralPublicUrl(10) }}" target="_blank"
                                       class="text-sm px-2 py-1 rounded border hover:bg-gray-50">Öffnen</a>
                                </div>
                            </li>
                        @endforeach
                    </ul>
                @else
                    <div class="text-sm text-gray-500">Keine Anhänge.</div>
                @endif
            </div>

            {{-- Admin-Kommentar (falls gefüllt) --}}
            @if($request->admin_comment)
                <div class="mt-6">
                    <div class="text-xs font-semibold text-gray-500 mb-1">Entscheidungs-Kommentar</div>
                    <div class="rounded border bg-gray-50 p-3 text-sm whitespace-pre-line">{{ $request->admin_comment }}</div>
                </div>
            @endif
        @endif
    </x-slot>

    <x-slot name="footer">
        <x-secondary-button wire:click="close">Schließen</x-secondary-button>

        @if($request && $request->status === 'pending')
            <x-button class="ml-2" wire:click="cancel">Stornieren</x-button>
        @endif

        @if($request)
            <x-danger-button class="ml-2" wire:click="delete">Löschen</x-danger-button>
        @endif
    </x-slot>
</x-dialog-modal>
