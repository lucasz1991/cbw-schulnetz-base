<div>
    <div class="p-6">
    <h1 class="text-2xl font-bold mb-4">
        <x-user.public-info :user="$participant" />
    </h1>
    <ul class="space-y-2">
        <li><strong>Email:</strong> {{ $participant->email }}</li>
        <li><strong>Geburtsdatum:</strong> {{ $participant->birth_date?->format('d.m.Y') }}</li>
        <li><strong>Kurs:</strong> {{ $participant->course->name ?? '-' }}</li>
        {{-- Weitere Felder hier --}}
    </ul>
</div>

</div>
