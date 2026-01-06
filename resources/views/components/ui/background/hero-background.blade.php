<div {{ $attributes->merge(['class' => 'relative overflow-hidden']) }}>
    {{-- Background gradient --}}
    <div class="absolute inset-0 bg-gradient-to-br from-blue-50 via-white to-emerald-50"></div>

    {{-- Glow elements --}}
    <div class="absolute -top-24 -right-24 h-80 w-80 rounded-full bg-blue-200/50 blur-3xl"></div>
    <div class="absolute -bottom-28 -left-28 h-80 w-80 rounded-full bg-emerald-200/50 blur-3xl"></div>

    {{-- Bottom wave --}}
    <svg class="absolute bottom-0 left-0 right-0 w-full text-white"
         viewBox="0 0 1440 120"
         preserveAspectRatio="none">
        <path fill="currentColor"
              d="M0,64L60,69.3C120,75,240,85,360,80C480,75,600,53,720,42.7C840,32,960,32,1080,42.7C1200,53,1320,75,1380,85.3L1440,96L1440,120L0,120Z"/>
    </svg>

    <div class="relative">
            {{ $slot }}
    </div>
</div>
