<div class="container mx-auto px-3 py-6 md:px-5">
    <div class="mb-6 flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-xl font-semibold text-gray-800">Berichtsheft-Archiv</h1>
            @if(\App\Services\ReportBooks\LocationPolicy::forBook($book)['mode'] === 'archive')
                <p class="mt-2 text-sm text-gray-600">{{ \App\Services\ReportBooks\LocationPolicy::NOTICE }}</p>
            @endif
            <p class="mt-2 max-w-2xl text-sm text-gray-600">Hier findest du deine freigegebenen Einträge. Das Archiv ist nur zum Lesen und Herunterladen verfügbar. Offene Einträge werden nicht angezeigt.</p>
        </div>
        @if($canUseEditor)
            <a href="{{ route('reportbook') }}" wire:navigate class="text-sm text-primary-700 underline underline-offset-4">Zur Berichtsheft-Bearbeitung</a>
        @endif
    </div>
    <div class="grid gap-5 lg:grid-cols-3">
        <aside class="space-y-3">
            <h2 class="text-sm font-semibold text-gray-700">Berichtshefte</h2>
            <div class="overflow-hidden rounded-lg border border-gray-300 bg-white">
                @foreach($books as $item)
                    <button type="button" wire:click="selectBook({{ $item->id }})" wire:key="archive-book-{{ $item->id }}"
                        aria-current="{{ $book->id === $item->id ? 'true' : 'false' }}"
                        class="block w-full border-b border-gray-200 px-4 py-3 text-left text-sm last:border-b-0 focus-visible:outline focus-visible:outline-2 focus-visible:outline-primary-500 {{ $book->id === $item->id ? 'bg-primary-50 text-primary-800' : 'text-gray-700 hover:bg-gray-50' }}">
                        <span class="block font-medium">{{ $item->course?->title ?? $item->title ?? 'Berichtsheft' }}</span>
                        <span class="mt-1 block text-xs text-gray-500">Heft #{{ $item->id }}@if($item->massnahme_id) · Maßnahme {{ $item->massnahme_id }}@endif</span>
                    </button>
                @endforeach
            </div>
            <x-buttons.button-basic wire:click="exportAll" wire:loading.attr="disabled" wire:target="exportAll" :size="'sm'" :mode="'secondary'" class="w-full">Alle freigegebenen Einträge herunterladen</x-buttons.button-basic>
        </aside>
        <section class="min-w-0 space-y-4 lg:col-span-2" aria-label="Freigegebene Berichte">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-gray-800">{{ $book->course?->title ?? $book->title ?? 'Berichtsheft' }}</h2>
                <x-buttons.button-basic wire:click="exportBook" wire:loading.attr="disabled" wire:target="exportBook" :size="'sm'" :mode="'secondary'">Freigegebenen Ausschnitt herunterladen</x-buttons.button-basic>
            </div>
            @foreach($entries as $entry)
                <article wire:key="archive-entry-{{ $entry->id }}" class="rounded-lg border border-gray-300 bg-white p-4">
                    <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                        <h3 class="font-medium text-gray-800">{{ $entry->entry_date->format('d.m.Y') }}</h3>
                        <span class="text-xs text-green-700">{{ $entry->automated_approval_audit_id ? 'Durch Standortregel freigegeben' : 'Freigegeben' }}</span>
                    </div>
                    <div class="whitespace-pre-wrap break-words text-sm leading-6 text-gray-700">{{ html_entity_decode(strip_tags(preg_replace('/<\s*(br\s*\/?|\/p|\/div)\s*>/i', "\n", (string) $entry->text)), ENT_QUOTES | ENT_HTML5, 'UTF-8') }}</div>
                    <button type="button" wire:click="exportEntry({{ $entry->id }})" wire:loading.attr="disabled" wire:target="exportEntry" class="mt-3 text-sm text-primary-700 underline underline-offset-4">Diesen Eintrag herunterladen</button>
                </article>
            @endforeach
        </section>
    </div>
</div>
