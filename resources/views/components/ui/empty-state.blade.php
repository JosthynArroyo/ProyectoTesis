@props([
    'title' => null,
    'message' => null,
])

<div {{ $attributes->merge(['class' => 'empty-state']) }}>
    @isset($icon)
        <div class="text-2xl text-slate-400">{{ $icon }}</div>
    @endisset
    @if($title)
        <h3 class="text-lg font-semibold text-slate-700">{{ $title }}</h3>
    @endif
    @if($message)
        <p class="text-sm text-slate-500">{{ $message }}</p>
    @endif
    {{ $slot }}
</div>