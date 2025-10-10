<div class="">
  <header class="container mx-auto md:px-5 py-6 flex items-start justify-between">
    <div>
      <h1 class="text-2xl font-semibold">{{ $course['title'] ?? '—' }}</h1>
      <p class="text-gray-600">{{ $course['room'] ?? '—' }} · {{ $course['zeitraum_fmt'] ?? '—' }}</p>
      @php
        $status = $course['status'] ?? 'Offen';
        $badge = [
          'Geplant' => 'bg-yellow-100 text-yellow-800',
          'Laufend' => 'bg-blue-100 text-blue-800',
          'Abgeschlossen' => 'bg-gray-100 text-gray-800',
          'Offen' => 'bg-gray-100 text-gray-800',
        ][$status] ?? 'bg-gray-100 text-gray-800';
      @endphp
      <span class="inline-block mt-1 px-2 py-0.5 rounded {{ $badge }}">{{ $status }}</span>
    </div>
    <div class="flex items-center gap-2">
      <x-buttons.button-basic :size="'sm'"
        @click="$dispatch('open-course-rating-modal', { course_id: '{{ $course['klassen_id'] }}' })">
        Bewerten
      </x-buttons.button-basic>
      @if($prev)
        <x-buttons.button-basic :size="'sm'"
          href="{{ route('user.program.course.show', ['klassenId' => $prev['klassen_id']]) }}"
          wire:navigate>← Vorheriger</x-buttons.button-basic>
      @endif
      @if($next)
        <x-buttons.button-basic :size="'sm'"
          href="{{ route('user.program.course.show', ['klassenId' => $next['klassen_id']]) }}"
          wire:navigate>Nächster →</x-buttons.button-basic>
      @endif
    </div>
  </header>

  <section class="container mx-auto md:px-5 pb-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="bg-white rounded-lg border shadow p-4">
        <p class="text-xs text-gray-500">Tage</p>
        <p class="text-2xl font-semibold">{{ $stats['tage'] ?? '—' }}</p>
      </div>
      <div class="bg-white rounded-lg border shadow p-4">
        <p class="text-xs text-gray-500">Einheiten (gesamt)</p>
        <p class="text-2xl font-semibold">{{ $stats['einheiten'] ?? '—' }}</p>
      </div>
      <div class="bg-white rounded-lg border shadow p-4">
        <p class="text-xs text-gray-500">Beginn</p>
        <p class="text-2xl font-semibold">{{ $stats['start'] ?? '—' }}</p>
      </div>
      <div class="bg-white rounded-lg border shadow p-4">
        <p class="text-xs text-gray-500">Ende</p>
        <p class="text-2xl font-semibold">{{ $stats['end'] ?? '—' }}</p>
      </div>
    </div>
  </section>


</div>
