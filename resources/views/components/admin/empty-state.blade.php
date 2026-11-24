@props([
    'icon' => 'info',
    'title',
    'description' => null,
    'actionUrl' => null,
    'actionLabel' => null,
])

<div class="admin-empty-state" role="status">
    <div class="admin-empty-icon">
        <x-icon :name="$icon" size="1.35em" />
    </div>
    <h4>{{ $title }}</h4>
    @if ($description)
        <p>{{ $description }}</p>
    @endif
    @if ($actionUrl && $actionLabel)
        <a class="admin-btn-outline admin-btn-sm" href="{{ $actionUrl }}">{{ $actionLabel }}</a>
    @endif
</div>
