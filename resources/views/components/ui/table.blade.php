@props([
    'head' => null,
])

<div {{ $attributes->merge(['class' => 'table-shell table-responsive-x']) }}>
    <table class="table">
        @if(isset($head))
            <thead>
                {{ $head }}
            </thead>
        @endif
        <tbody>
            {{ $slot }}
        </tbody>
    </table>
</div>
