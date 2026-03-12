<div class="py-8 container px-5 mx-auto"
     x-data="{
       group: 'course-{{ $course->id }}',
       storageKey: 'acc-course-{{ $course->id }}',
       activeId: null,
       init() {
         let raw = null;
         try {
           raw = localStorage.getItem(this.storageKey);
         } catch (e) {
           this.activeId = 'roter-faden';
           return;
         }

         if (raw === null) {
           this.activeId = 'roter-faden';
           return;
         }

         try {
           this.activeId = JSON.parse(raw);
         } catch (e) {
           this.activeId = raw || 'roter-faden';
           try {
             localStorage.setItem(this.storageKey, JSON.stringify(this.activeId));
           } catch (e2) {}
         }
       },
       setActive(id) {
         this.activeId = id;
         try {
           localStorage.setItem(this.storageKey, JSON.stringify(id));
         } catch (e) {}
       },
       toggleActive(id) {
         this.setActive(this.activeId === id ? null : id);
       }
     }"
     @accordion-set.window="if ($event.detail.group === group) setActive($event.detail.id)"
     @accordion-toggle.window="if ($event.detail.group === group) toggleActive($event.detail.id)">
<x-alert>
  <p class="text-sm">
    Vorschau & Download der Bausteinmedien und des „Roten Fadens“.
    Direkte Links sind aus Sicherheitsgründen nur kurz gültig.
    Bei Anzeigeproblemen bitte „In neuem Tab öffnen“ nutzen oder den Dozenten kontaktieren.
  </p>
</x-alert>

  <div class="mb-8 space-y-8">
    {{-- Bildungsmittel --}}
    <x-ui.dropdown.course-dropdown group="course-{{ $course->id }}" item-id="resources">
      <x-slot name="trigger"><h1>Bildungsmittel</h1></x-slot>

      <x-slot name="content">
        @php
          $materials = collect($course->materials ?? [])->values();
          $materialsCount = $materials->count();
        @endphp

        <div class="my-4 space-y-5">

          @if($materialsCount > 0)
            <div class="grid gap-3">
              @foreach($materials as $index => $m)
                @php
                  $title = trim((string) ($m['titel'] ?? ''));
                  $subtitle = trim((string) ($m['titel2'] ?? ''));
                  $publisher = trim((string) ($m['verlag'] ?? ''));
                  $isbn = trim((string) ($m['isbn'] ?? ''));
                @endphp

                <article class="group relative overflow-hidden rounded-2xl border border-slate-200 bg-white p-4 shadow-sm transition duration-200 hover:-translate-y-0.5 hover:border-blue-200 hover:shadow-md">
                  <div class="absolute -right-8 -top-10 h-24 w-24 rounded-full bg-blue-100/40 transition group-hover:scale-110"></div>

                  <div class="relative">
                    <div class="flex items-start gap-3">
                      <span class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-full bg-slate-700 text-xs font-semibold text-white">
                        {{ $index + 1 }}
                      </span>

                      <div class="min-w-0">
                        <h3 class="text-sm font-semibold leading-5 text-slate-900 break-words">
                          {{ $title !== '' ? $title : 'Unbenanntes Bildungsmittel' }}
                        </h3>

                        @if($subtitle !== '')
                          <p class="mt-1 text-xs leading-4 text-slate-600 break-words">{{ $subtitle }}</p>
                        @endif
                      </div>
                    </div>

                    <div class="mt-4 flex flex-wrap gap-2">
                      @if($publisher !== '')
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-700">
                          <i class="fal fa-building text-[10px]"></i>
                          {{ $publisher }}
                        </span>
                      @endif

                      @if($isbn !== '')
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-slate-200 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-700">
                          <i class="fal fa-barcode-alt text-[10px]"></i>
                          ISBN: {{ $isbn }}
                        </span>
                      @endif

                      @if($publisher === '' && $isbn === '')
                        <span class="inline-flex items-center gap-1.5 rounded-full border border-dashed border-slate-300 bg-slate-50 px-2.5 py-1 text-[11px] font-medium text-slate-500">
                          <i class="fal fa-info-circle text-[10px]"></i>
                          Keine Zusatzdaten
                        </span>
                      @endif
                    </div>
                  </div>
                </article>
              @endforeach
            </div>
          @else
            <div class="rounded-2xl border border-dashed border-slate-300 bg-slate-50/80 p-5 shadow-sm">
              <div class="flex items-start gap-3">
                <span class="inline-flex h-9 w-9 items-center justify-center rounded-lg bg-white text-slate-500 shadow-sm">
                  <i class="fal fa-box-open text-sm"></i>
                </span>
                <div>
                  <h3 class="text-sm font-semibold text-slate-800">Aktuell keine Bildungsmittel hinterlegt</h3>
                  <p class="mt-1 text-xs text-slate-600">Sobald Materialien zugeordnet wurden, erscheinen sie hier inkl. Bestätigung.</p>
                </div>
              </div>
            </div>
          @endif

          <livewire:user.program.course.materials-acknowledgement :course="$course" lazy />
        </div>
      </x-slot>
    </x-ui.dropdown.course-dropdown>

    {{-- Roter Faden (nur Preview & Download) --}}
    <x-ui.dropdown.course-dropdown group="course-{{ $course->id }}" item-id="roter-faden">
      <x-slot name="trigger"><h1>Roter Faden</h1></x-slot>

      <x-slot name="content">
        <div class="p-4 space-y-4">
          @if($roterFaden)
            <div class="md:flex items-center flex-wrap justify-between">
              <div class="text-base max-md:mb-4 flex items-center gap-2">
                <div class="flex-shrink-0 w-12 h-12 flex items-center justify-center">
                  <img class="w-12 h-12 object-contain" src="{{ $roterFaden->icon_or_thumbnail }}" alt="Datei-Icon">
                </div>
                <div class="flex-1 min-w-0">
                  <div class="font-medium truncate">{{ $roterFaden->name }}</div>
                  <div class="font-medium text-xs text-gray-500">{{ $roterFaden->size_formatted }}</div>
                </div>
              </div>

              <div class="flex items-center flex-wrap gap-2">
                <x-buttons.btn-group.btn-group>
                  <x-buttons.btn-group.btn-group-item
                    @click="window.dispatchEvent(new CustomEvent('filepool-preview', { detail: { id: {{ $roterFaden->id }} } }))"
                  >
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                    Vorschau
                  </x-buttons.btn-group.btn-group-item>

                  <x-buttons.btn-group.btn-group-item wire:click="downloadFile({{ $roterFaden->id }})">
                    <svg xmlns="http://www.w3.org/2000/svg" class="w-4 aspect-square mr-2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Download
                  </x-buttons.btn-group.btn-group-item>
                </x-buttons.btn-group.btn-group>
              </div>
            </div>
          @else
            <p class="text-gray-600 text-sm">Noch kein „Roter Faden“ vorhanden.</p>
          @endif
        </div>
      </x-slot>
    </x-ui.dropdown.course-dropdown>

    <x-ui.dropdown.course-dropdown group="course-{{ $course->id }}" item-id="medien">
      <x-slot name="trigger">
        <div class="flex items-center space-x-3">
          <h1>Medien</h1>
          <div class="text-blue-600 bg-white h-6 w-6 aspect-square rounded-full flex justify-center items-center">
            <span class="text-xs font-semibold">{{ optional($course->filePool)->files?->count() ?? 0 }}</span>
          </div>
        </div>
      </x-slot>
      <x-slot name="content">
        <livewire:tools.file-pools.manage-file-pools
          :modelType="\App\Models\Course::class"
          :modelId="$course->id"
          :readOnly="true"
          lazy
        />
      </x-slot>
    </x-ui.dropdown.course-dropdown>
  </div>
</div>
