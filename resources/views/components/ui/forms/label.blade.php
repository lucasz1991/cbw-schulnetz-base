@props(['value'])

<label {{ $attributes->merge(['class' => 'block text-sm text-secondary font-semibold mb-2']) }}>
    {{ $value ?? $slot }}
</label>
