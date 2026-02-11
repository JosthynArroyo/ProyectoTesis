@props([
    'title' => null,
    'subtitle' => null,
    'padding' => 'p-6',
])

<div {{ $attributes->merge(['class' => 'card overflow-hidden']) }}>
    @if(isset($header))
        <div class="border-b border-slate-100 bg-white/80 px-6 py-4">
            {{ $header }}
        </div>
    @elseif($title || $subtitle || isset($actions))
        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-100 bg-white/80 px-6 py-4">
            <div>
                @if($title)
                    <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
                @endif
                @if($subtitle)
                    <p class="text-sm text-slate-500">{{ $subtitle }}</p>
                @endif
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    @endif

    <div class="{{ $padding }}">
        {{ $slot }}
    </div>
</div>