
<div class="mt-6">
  @php
    $hasMaterials = collect($course->materials ?? [])->isNotEmpty();
  @endphp
  @if($this->alreadyAcknowledged)
    <div class="rounded-2xl border border-emerald-300 bg-gradient-to-br from-emerald-50 via-white to-emerald-100/60 p-4 shadow-sm">
      <div class="flex items-start gap-3">
        <div class="relative mt-0.5 h-9 w-9 shrink-0">
          <span class="absolute inset-0 inline-flex rounded-full bg-emerald-300/70 animate-ping"></span>
          <span class="relative z-10 inline-flex h-9 w-9 items-center justify-center rounded-full border border-emerald-200 bg-white text-emerald-700 shadow-sm">
            <i class="fal fa-check text-sm animate-pulse"></i>
          </span>
        </div>

        <div>
          <h3 class="text-sm font-semibold text-emerald-900">Bildungsmittel bestätigt</h3>
          <p class="mt-1 text-xs text-emerald-800">
            Die Bereitstellung der Kursmaterialien wurde erfolgreich bestätigt und digital dokumentiert.
          </p>
        </div>
      </div>
    </div>
  @else
    <div class="rounded-2xl border border-amber-300 bg-gradient-to-br from-amber-50 via-white to-amber-100/60 p-4 shadow-sm">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="flex items-start gap-3">
          <div class="relative mt-0.5 h-9 w-9 shrink-0">
            <span class="absolute inset-0 inline-flex rounded-full bg-amber-300/70 animate-ping"></span>
            <span class="relative z-10 inline-flex h-9 w-9 items-center justify-center rounded-full border border-amber-200 bg-white text-amber-700 shadow-sm">
              <i class="fal fa-clipboard-check text-sm animate-pulse"></i>
            </span>
          </div>

          <div>
            <h3 class="text-sm font-semibold text-amber-900">Bestätigung ausstehend</h3>
            <p class="mt-1 text-xs text-amber-800">
              Bitte bestätige den Erhalt der oben aufgeführten Bildungsmittel für diesen Kurs.
            </p>
            @if(!$hasMaterials)
              <p class="mt-2 text-xs font-medium text-amber-700">
                Aktuell sind noch keine Materialien hinterlegt.
              </p>
            @endif
          </div>
        </div>

        <button
          type="button"
          class="inline-flex items-center justify-center gap-2 rounded-xl bg-amber-500 px-4 py-2 text-sm font-semibold text-white shadow-sm transition hover:bg-amber-600 disabled:cursor-not-allowed disabled:opacity-60"
          wire:click="startAcknowledgement"
          wire:loading.attr="disabled"
          wire:target="startAcknowledgement"
        >
          <i class="fal fa-signature text-sm"></i>
          <span>Jetzt bestätigen</span>
        </button>
      </div>
    </div>
  @endif

  {{-- Gemeinsames Signature-Modal (öffnet über openSignatureForm) --}}
  <livewire:tools.signatures.signature-form
      :key="'signature-form-materials-'.$course->id"
      lazy
  />
</div>
