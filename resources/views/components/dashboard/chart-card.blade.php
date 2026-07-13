@props([
    'chartKey',
    'chartType' => 'line',
    'title' => '',
    'subtitle' => null,
    'emptyText' => 'No hay datos para el período seleccionado',
    'heightClass' => 'min-h-[340px]',
])

<div {{ $attributes->merge(['class' => 'card dashboard-chart-card p-5']) }}>
    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ $title }}</h3>
            @if($subtitle)
                <p class="mt-1 text-sm text-gray-500">{{ $subtitle }}</p>
            @endif
        </div>
        @if(isset($controls))
            <div class="flex flex-wrap items-center gap-2">
                {{ $controls }}
            </div>
        @endif
    </div>

    <div class="dashboard-chart-shell mt-4" data-chart-shell>
        <div
            data-chart-key="{{ $chartKey }}"
            data-chart-type="{{ $chartType }}"
            class="w-full {{ $heightClass }}"
            aria-label="{{ $title }}"
        ></div>
        <div class="dashboard-chart-empty hidden" data-chart-empty>
            <x-ui.empty-state
                title="Sin datos"
                :message="$emptyText"
            />
        </div>
    </div>
</div>
