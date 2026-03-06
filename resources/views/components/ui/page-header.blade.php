@props(['title' => null, 'subtitle' => null])

<div class="page-header">
    <div class="page-header__info">
        @if($title)
            <h2>{{ $title }}</h2>
        @endif
        @if($subtitle)
            <p>{{ $subtitle }}</p>
        @endif
    </div>
    @if($slot->isNotEmpty())
        <div class="page-header__actions">
            {{ $slot }}
        </div>
    @endif
</div>
