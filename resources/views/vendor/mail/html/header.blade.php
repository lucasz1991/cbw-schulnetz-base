@props(['url'])

@php
    $logoPath = public_path('site-images/logo.png');
    $logoSource = file_exists($logoPath)
        ? 'data:image/png;base64,'.base64_encode(file_get_contents($logoPath))
        : null;
@endphp

<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block; width: 225px;">
@if($logoSource)
<img src="{{ $logoSource }}"
     alt="CBW College Berufliche Weiterbildung"
     width="225"
     style="border: 0; display: block; height: auto; max-width: 225px; outline: none; text-decoration: none; width: 100%;">
@else
{{ $slot }}
@endif
</a>
</td>
</tr>
