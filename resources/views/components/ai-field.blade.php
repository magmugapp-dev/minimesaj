@props(['label', 'hint' => null])

<label {{ $attributes->merge(['class' => 'block text-sm font-semibold text-gray-700']) }}>
    <span>{{ $label }}</span>
    @if ($hint)
        <span class="mt-0.5 block text-xs font-medium leading-5 text-gray-500">{{ $hint }}</span>
    @endif
    {{ $slot }}
</label>
