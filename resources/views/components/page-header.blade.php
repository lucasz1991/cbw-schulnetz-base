    @php
        $headerImageUrl = asset('site-images/home-Slider_-_Studenten.jpg');
        if (!empty($header_image)) {
            if (preg_match('~^https?://~i', $header_image)) {
                $headerImageUrl = $header_image;
            } else {
                $normalized = ltrim($header_image, '/');
                $headerImageUrl = str_starts_with($normalized, 'storage/')
                    ? "{$app_base_url}/{$normalized}"
                    : "{$app_base_url}/storage/{$normalized}";
            }
        }
    @endphp

    @if ($isWebPage && $showHeader)
        <header class="relative bg-cover bg-center min-h-16  md:px-8 " 
        style="background-image: url('{{ $headerImageUrl }}');">
            <div class="absolute inset-0 bg-white opacity-60"></div>
            <div class="relative container mx-auto px-5 pb-8 pt-8 text-xl  space-x-6 flex justify-start  items-center">
                <x-back-button />
                <h1 class=" text-xl text-gray-800 leading-tight flex items-center">
                    {{ $title }}
                    
                    @if (!empty($icon))
                        <div class="pageheader-icon w-12 aspect-square text-[#333] ml-10  inline opacity-30">
                            {!! $icon !!}
                        </div>
                    @endif
                </h1>
            </div>
        </header>
    @endif
