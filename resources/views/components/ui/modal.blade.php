@props([
    'id',
    'title' => null,
    'size' => 'max-w-2xl',
])

<div id="{{ $id }}" tabindex="-1" aria-hidden="true" class="fixed inset-0 z-50 hidden overflow-y-auto overflow-x-hidden p-4">
    <div class="relative mx-auto w-full {{ $size }}">
        <div class="card shadow-xl">
            <div class="flex items-start justify-between border-b border-slate-100 px-6 py-4">
                <div>
                    @if($title)
                        <h3 class="text-lg font-semibold text-slate-900">{{ $title }}</h3>
                    @endif
                    @isset($subtitle)
                        <p class="text-sm text-slate-500">{{ $subtitle }}</p>
                    @endisset
                </div>
                <button type="button" class="btn btn-ghost px-2" data-modal-hide="{{ $id }}" aria-label="Cerrar">
                    <i class="ri-close-line text-lg"></i>
                </button>
            </div>
            <div class="p-6">
                {{ $slot }}
            </div>
            @isset($footer)
                <div class="border-t border-slate-100 px-6 py-4">
                    {{ $footer }}
                </div>
            @endisset
        </div>
    </div>
</div>