@props([
  'calendar' => [],
  'title' => 'Agenda semanal',
  'subtitle' => null,
  'eyebrow' => null,
  'legend' => [],
  'emptyTitle' => 'Sin eventos',
  'emptyMessage' => 'No hay registros para esta semana.',
  'range' => null,
])

@php
  $days = $calendar['days'] ?? [];
  $rows = $calendar['rows'] ?? [];
  $backgroundEvents = $calendar['background_events'] ?? [];
  $events = $calendar['events'] ?? [];
  $rangeLabel = $range ?? ($calendar['range_label'] ?? null);
  $mobileEntries = collect($backgroundEvents)
    ->map(fn (array $event) => $event + ['layer' => 'background'])
    ->merge(collect($events)->map(fn (array $event) => $event + ['layer' => 'foreground']))
    ->sortBy([
      ['date', 'asc'],
      ['start', 'asc'],
      ['layer', 'asc'],
      ['title', 'asc'],
    ])
    ->groupBy('date');
  $isEmpty = empty($backgroundEvents) && empty($events);
@endphp

<section {{ $attributes->class(['weekly-schedule']) }}>
  <div class="weekly-schedule__header">
    <div class="weekly-schedule__heading">
      @if($eyebrow)
        <p class="weekly-schedule__eyebrow">{{ $eyebrow }}</p>
      @endif
      <h2 class="weekly-schedule__title">{{ $title }}</h2>
      @if($subtitle)
        <p class="weekly-schedule__subtitle">{{ $subtitle }}</p>
      @endif
    </div>

    <div class="weekly-schedule__header-actions">
      @if($rangeLabel)
        <span class="weekly-schedule__range">
          <i class="ri-calendar-schedule-line" aria-hidden="true"></i>
          {{ $rangeLabel }}
        </span>
      @endif

      @isset($actions)
        <div class="weekly-schedule__actions">
          {{ $actions }}
        </div>
      @endisset
    </div>
  </div>

  @isset($toolbar)
    <div class="weekly-schedule__toolbar">
      {{ $toolbar }}
    </div>
  @endisset

  @if(!empty($legend))
    <div class="weekly-schedule__legend" aria-label="Leyenda del calendario">
      @foreach($legend as $item)
        <span class="weekly-schedule__legend-item">
          <span class="weekly-schedule__legend-swatch weekly-schedule__legend-swatch--{{ $item['tone'] ?? 'slate' }} {{ ($item['variant'] ?? 'solid') === 'soft' ? 'is-soft' : '' }}"></span>
          {{ $item['label'] ?? '' }}
        </span>
      @endforeach
    </div>
  @endif

  <div class="weekly-schedule__viewport" role="region" aria-label="{{ $title }}">
    <div class="weekly-schedule__board" style="--week-row-count: {{ $calendar['row_count'] ?? 1 }};">
      <div class="weekly-schedule__corner">
        <span>Hora</span>
      </div>

      @foreach($days as $day)
        <header
          class="weekly-schedule__day-head {{ !empty($day['is_today']) ? 'is-today' : '' }}"
          style="grid-column: {{ $loop->iteration + 1 }}; grid-row: 1;"
        >
          <span class="weekly-schedule__day-short">{{ $day['day_short'] ?? '' }}</span>
          <strong class="weekly-schedule__day-number">{{ $day['day_number'] ?? '' }}</strong>
          <span class="weekly-schedule__day-name">{{ $day['day_name'] ?? '' }}</span>
        </header>
      @endforeach

      @foreach($rows as $row)
        <div
          class="weekly-schedule__time {{ !empty($row['is_major']) ? 'is-major' : '' }}"
          style="grid-column: 1; grid-row: {{ $row['grid_row'] }};"
        >
          {{ $row['label'] ?? '' }}
        </div>
      @endforeach

      @foreach($days as $day)
        @foreach($rows as $row)
          <div
            class="weekly-schedule__cell {{ !empty($row['is_major']) ? 'is-major' : '' }} {{ !empty($day['is_today']) ? 'is-today' : '' }}"
            style="grid-column: {{ $loop->parent->iteration + 1 }}; grid-row: {{ $row['grid_row'] }};"
            aria-hidden="true"
          ></div>
        @endforeach
      @endforeach

      @foreach($backgroundEvents as $event)
        <div
          class="weekly-schedule__event weekly-schedule__event--background weekly-schedule__event--{{ $event['tone'] ?? 'slate' }}"
          style="grid-column: {{ $event['column'] }}; grid-row: {{ $event['row_start'] }} / span {{ $event['row_span'] }}; --event-lane-index: {{ $event['lane_index'] ?? 0 }}; --event-lane-count: {{ $event['lane_count'] ?? 1 }};"
        >
          @if(!empty($event['eyebrow']))
            <span class="weekly-schedule__event-eyebrow">{{ $event['eyebrow'] }}</span>
          @endif
          <strong class="weekly-schedule__event-title">{{ $event['title'] }}</strong>
          @if(!empty($event['subtitle']))
            <span class="weekly-schedule__event-subtitle">{{ $event['subtitle'] }}</span>
          @endif
        </div>
      @endforeach

      @foreach($events as $event)
        @if(!empty($event['url']))
          <a
            href="{{ $event['url'] }}"
            class="weekly-schedule__event weekly-schedule__event--card weekly-schedule__event--{{ $event['tone'] ?? 'slate' }} {{ $event['classes'] ?? '' }}"
            style="grid-column: {{ $event['column'] }}; grid-row: {{ $event['row_start'] }} / span {{ $event['row_span'] }}; --event-lane-index: {{ $event['lane_index'] ?? 0 }}; --event-lane-count: {{ $event['lane_count'] ?? 1 }};"
          >
            @if(!empty($event['eyebrow']))
              <span class="weekly-schedule__event-eyebrow">{{ $event['eyebrow'] }}</span>
            @endif
            <strong class="weekly-schedule__event-title">{{ $event['title'] }}</strong>
            @if(!empty($event['subtitle']))
              <span class="weekly-schedule__event-subtitle">{{ $event['subtitle'] }}</span>
            @endif
            <span class="weekly-schedule__event-time">{{ $event['start'] }} - {{ $event['end'] }}</span>
            @if(!empty($event['meta']))
              <span class="weekly-schedule__event-meta">{{ $event['meta'] }}</span>
            @endif
          </a>
        @else
          <article
            class="weekly-schedule__event weekly-schedule__event--card weekly-schedule__event--{{ $event['tone'] ?? 'slate' }} {{ $event['classes'] ?? '' }}"
            style="grid-column: {{ $event['column'] }}; grid-row: {{ $event['row_start'] }} / span {{ $event['row_span'] }}; --event-lane-index: {{ $event['lane_index'] ?? 0 }}; --event-lane-count: {{ $event['lane_count'] ?? 1 }};"
          >
            @if(!empty($event['eyebrow']))
              <span class="weekly-schedule__event-eyebrow">{{ $event['eyebrow'] }}</span>
            @endif
            <strong class="weekly-schedule__event-title">{{ $event['title'] }}</strong>
            @if(!empty($event['subtitle']))
              <span class="weekly-schedule__event-subtitle">{{ $event['subtitle'] }}</span>
            @endif
            <span class="weekly-schedule__event-time">{{ $event['start'] }} - {{ $event['end'] }}</span>
            @if(!empty($event['meta']))
              <span class="weekly-schedule__event-meta">{{ $event['meta'] }}</span>
            @endif
          </article>
        @endif
      @endforeach

      @if($isEmpty)
        <div class="weekly-schedule__empty">
          <strong>{{ $emptyTitle }}</strong>
          <p>{{ $emptyMessage }}</p>
        </div>
      @endif
    </div>
  </div>

  <div class="weekly-schedule__mobile" role="region" aria-label="{{ $title }} móvil">
    @if($isEmpty)
      <div class="weekly-schedule__mobile-empty">
        <strong>{{ $emptyTitle }}</strong>
        <p>{{ $emptyMessage }}</p>
      </div>
    @else
      @foreach($days as $day)
        @php
          $dayEvents = $mobileEntries->get($day['key'] ?? '', collect());
        @endphp

        <article class="weekly-schedule__mobile-day {{ !empty($day['is_today']) ? 'is-today' : '' }}">
          <header class="weekly-schedule__mobile-day-head">
            <div class="weekly-schedule__mobile-day-meta">
              <span class="weekly-schedule__mobile-day-short">{{ $day['day_short'] ?? '' }}</span>
              <div class="weekly-schedule__mobile-day-date">
                <strong>{{ $day['day_number'] ?? '' }}</strong>
                <span>{{ $day['day_name'] ?? '' }}</span>
              </div>
            </div>

            <span class="weekly-schedule__mobile-day-count">
              {{ $dayEvents->count() }} {{ $dayEvents->count() === 1 ? 'registro' : 'registros' }}
            </span>
          </header>

          @if($dayEvents->isEmpty())
            <p class="weekly-schedule__mobile-day-empty">Sin actividad en este día.</p>
          @else
            <div class="weekly-schedule__mobile-list">
              @foreach($dayEvents as $event)
                @php
                  $mobileEventClasses = trim(
                    'weekly-schedule__event weekly-schedule__event--card weekly-schedule__event--'.$event['tone'].' '.
                    (!empty($event['classes']) ? $event['classes'].' ' : '').
                    (($event['layer'] ?? 'foreground') === 'background' ? 'weekly-schedule__event--mobile-background' : '')
                  );
                @endphp

                @if(!empty($event['url']))
                  <a href="{{ $event['url'] }}" class="{{ $mobileEventClasses }}">
                    @if(!empty($event['eyebrow']))
                      <span class="weekly-schedule__event-eyebrow">{{ $event['eyebrow'] }}</span>
                    @endif
                    <strong class="weekly-schedule__event-title">{{ $event['title'] }}</strong>
                    @if(!empty($event['subtitle']))
                      <span class="weekly-schedule__event-subtitle">{{ $event['subtitle'] }}</span>
                    @endif
                    <span class="weekly-schedule__event-time">{{ $event['start'] }} - {{ $event['end'] }}</span>
                    @if(!empty($event['meta']))
                      <span class="weekly-schedule__event-meta">{{ $event['meta'] }}</span>
                    @endif
                  </a>
                @else
                  <article class="{{ $mobileEventClasses }}">
                    @if(!empty($event['eyebrow']))
                      <span class="weekly-schedule__event-eyebrow">{{ $event['eyebrow'] }}</span>
                    @endif
                    <strong class="weekly-schedule__event-title">{{ $event['title'] }}</strong>
                    @if(!empty($event['subtitle']))
                      <span class="weekly-schedule__event-subtitle">{{ $event['subtitle'] }}</span>
                    @endif
                    <span class="weekly-schedule__event-time">{{ $event['start'] }} - {{ $event['end'] }}</span>
                    @if(!empty($event['meta']))
                      <span class="weekly-schedule__event-meta">{{ $event['meta'] }}</span>
                    @endif
                  </article>
                @endif
              @endforeach
            </div>
          @endif
        </article>
      @endforeach
    @endif
  </div>
</section>
