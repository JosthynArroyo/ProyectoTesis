@props(['paginator'])

@if($paginator)
    <div {{ $attributes->merge(['class' => 'flex items-center justify-between gap-3 pt-4 text-sm text-slate-600']) }}>
        {{ $paginator->links() }}
    </div>
@endif