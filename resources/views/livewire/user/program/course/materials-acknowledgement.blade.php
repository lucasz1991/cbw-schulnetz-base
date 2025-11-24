<div class="mt-6">
  @if($this->alreadyAcknowledged)
    <div class="rounded border border-emerald-300 bg-emerald-50 p-3 text-emerald-800 text-sm">
      ✅ Bereitstellung der Kursmaterialien wurde bestätigt.
    </div>
  @else
    <div class="rounded border p-4 bg-white">
      <p class="text-sm text-gray-700 mb-3">
        Ich bestätige den Erhalt der oben aufgeführten Bildungsmittel zu diesem Kurs.
      </p>

      <button type="button"
              class="px-3 py-1.5 rounded bg-blue-600 text-white text-sm hover:bg-blue-700"
              wire:click="startAcknowledgement">
        Jetzt bestätigen &amp; unterschreiben
      </button>
    </div>
  @endif

  {{-- Gemeinsames Signature-Modal (öffnet über openSignatureForm) --}}
  <livewire:tools.signatures.signature-form
      :key="'signature-form-materials-'.$course->id"
      lazy
  />
</div>
