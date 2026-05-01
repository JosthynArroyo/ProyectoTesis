@props([
    'title' => null,
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    @isset($icon)
        <div class="text-2xl text-gray-400">{{ $icon }}</div>
    @endisset
    @if($title)
        <h3 class="text-lg font-semibold text-gray-700">{{ $title }}</h3>
    @endif
    @if($message)
        <p class="text-sm text-gray-500">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>