@props([
    'variant' => 'info',
    'icon' => null,
])

<span {{ $attributes->merge(['class' => 'admin-badge is-' . $variant]) }}>
    @if ($icon)
        <x-icon :name="$icon" size="0.95em" />
    @endif
    <span>{{ $slot }}</span>
</span>
