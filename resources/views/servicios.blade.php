@extends('layouts.navbar')

@section('title', 'Servicios - '.$clinicIdentity->name())

@section('main')
@php
    use App\Support\ServicePageCatalog;

    $heroTitle = $siteSettings->get('services.title', 'Especialidades y servicios disponibles');
    $heroSubtitle = $siteSettings->get('services.subtitle', 'Explora las opciones de la clinica y agenda una cita segun los horarios registrados en el sistema.');
    $heroImagePath = $siteSettings->get('services.hero_image', ServicePageCatalog::heroImagePath());
    // Only substitute the local static fallback when the DB value is empty or
    // equals the static local asset path exactly.
    // Any path under images/services/ is a real R2 upload — do NOT replace it.
    $heroIsLocalDefault = (
        empty($heroImagePath)
        || $heroImagePath === ServicePageCatalog::heroImagePath()
    );
    if ($heroIsLocalDefault) {
        $heroImagePath = ServicePageCatalog::heroImagePath();
    }
    $heroImage = $imageUrl->variants($heroImagePath, 'services', 'banner', 'public_hero');
    $serviceCatalog = ServicePageCatalog::catalog();
    $fallbackMeta = ServicePageCatalog::fallback();
@endphp

<div class="flex min-h-[calc(100vh-4.5rem)] flex-col">
    <section class="section-pad section-pad--first">
        <div class="page-shell">
            <div class="space-y-6 lg:space-y-8">
                <section class="glass-panel overflow-hidden rounded-[2rem] border border-white/80 bg-white/95 p-0 shadow-[0_28px_70px_rgba(15,23,42,0.12)]">
                    <div class="grid min-w-0 lg:grid-cols-[minmax(0,1fr)_minmax(320px,0.92fr)] lg:items-stretch">
                        <div class="flex min-w-0 flex-col justify-center px-6 py-8 sm:px-8 sm:py-10 lg:px-12 lg:py-12">
                            <p class="text-xs font-semibold uppercase tracking-[0.24em] text-gray-700">Servicios</p>
                            <h1 class="mt-4 max-w-xl text-3xl font-semibold text-gray-900 sm:text-4xl lg:text-[2.8rem] lg:leading-[1.05]">
                                {{ $heroTitle }}
                            </h1>
                            <p class="mt-4 max-w-2xl text-sm leading-7 text-gray-600 sm:text-base">
                                {{ $heroSubtitle }}
                            </p>

                            <div class="mt-6 flex flex-wrap gap-3 text-sm text-gray-600">
                                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-100 px-3 py-2 font-medium text-gray-700">
                                    <i class="ri-calendar-check-line text-base" aria-hidden="true"></i>
                                    Agenda segun disponibilidad
                                </span>
                                <span class="inline-flex items-center gap-2 rounded-full border border-gray-200 bg-gray-50 px-3 py-2 font-medium text-gray-600">
                                    <i class="ri-shield-check-line text-base" aria-hidden="true"></i>
                                    Especialidades activas del sistema
                                </span>
                            </div>

                            <div class="mt-8">
                                @auth
                                    <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary btn-lg">
                                        <i class="ri-calendar-check-line" aria-hidden="true"></i>
                                        {{ $siteSettings->get('services.cta_text', 'Agendar cita') }}
                                    </a>
                                @else
                                    <a href="{{ url('/') . '?login=1' }}" class="btn btn-primary btn-lg" data-login-trigger>
                                        <i class="ri-login-circle-line" aria-hidden="true"></i>
                                        {{ $siteSettings->get('services.cta_text', 'Agendar cita') }}
                                    </a>
                                @endauth
                            </div>
                        </div>

                        <div class="min-w-0 border-t border-gray-100 bg-[radial-gradient(circle_at_top,_rgba(20,184,166,0.16),_transparent_52%),linear-gradient(180deg,_#f8fafc_0%,_#ffffff_100%)] lg:border-t-0 lg:border-l">
                            <div class="h-full min-h-[260px] px-5 pb-5 pt-0 sm:min-h-[320px] sm:px-6 sm:pb-6 lg:min-h-full lg:p-6">
                                <div class="h-full overflow-hidden rounded-[1.75rem] bg-white shadow-[0_18px_45px_rgba(15,23,42,0.12)]">
                                    <img
                                        src="{{ $heroImage['medium'] }}"
                                        @if($heroImage['srcset']) srcset="{{ $heroImage['srcset'] }}" sizes="(min-width: 1024px) 40vw, 100vw" @endif
                                        alt="Equipo medico atendiendo a una paciente en la seccion de servicios"
                                        class="h-full w-full object-cover object-center"
                                        loading="eager"
                                        decoding="async"
                                        fetchpriority="high"
                                    >
                                </div>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="rounded-[1.75rem] border border-gray-200/80 bg-white/95 p-4 shadow-[0_20px_50px_rgba(15,23,42,0.08)] sm:p-5">
                    <form class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_auto]" method="GET" action="{{ route('servicios.index') }}" role="search">
                        <input type="hidden" name="tipo" value="{{ $tipo ?? 'all' }}">

                        <label class="flex min-w-0 items-center gap-3 rounded-2xl border border-gray-200 bg-gray-50/80 px-4 py-3 shadow-sm shadow-gray-200/50 transition focus-within:border-gray-300 focus-within:bg-white focus-within:ring-4 focus-within:ring-gray-200">
                            <i class="ri-search-line text-lg text-gray-400" aria-hidden="true"></i>
                            <input
                                type="search"
                                name="q"
                                value="{{ $q ?? '' }}"
                                placeholder="Buscar servicio o especialidad..."
                                class="w-full min-w-0 appearance-none border-0 bg-transparent text-sm text-gray-700 placeholder:text-gray-400 outline-none ring-0 focus:border-transparent focus:outline-none focus:ring-0"
                            >
                        </label>

                        <div class="flex flex-wrap items-center gap-2" role="tablist" aria-label="Tipo de servicio">
                            @foreach($serviceTypes as $value => $label)
                                <a
                                    class="{{ ($tipo ?? 'all') === $value ? 'border-gray-200 bg-gray-100 text-gray-700 shadow-sm shadow-gray-200' : 'border-gray-200 bg-white text-gray-500 hover:border-gray-300 hover:bg-gray-50 hover:text-gray-700' }} inline-flex min-h-[44px] items-center justify-center rounded-full border px-4 py-2 text-sm font-semibold transition"
                                    href="{{ route('servicios.index', array_filter(['q' => $q ?? '', 'tipo' => $value === 'all' ? null : $value], fn ($item) => filled($item))) }}"
                                    role="tab"
                                    aria-selected="{{ ($tipo ?? 'all') === $value ? 'true' : 'false' }}"
                                >
                                    {{ $label }}
                                </a>
                            @endforeach
                        </div>
                    </form>

                    @if(($q ?? '') !== '' || ($tipo ?? 'all') !== 'all')
                        <div class="mt-4">
                            <a class="btn btn-ghost btn-sm" href="{{ route('servicios.index') }}">
                                <i class="ri-refresh-line" aria-hidden="true"></i>
                                Limpiar filtros
                            </a>
                        </div>
                    @endif
                </section>

                <div class="grid gap-6 md:grid-cols-2 xl:grid-cols-3">
                    @forelse($especialidades as $esp)
                        @php
                            $normalizedName = ServicePageCatalog::normalizeName($esp->nombre);
                            $meta = $serviceCatalog[$normalizedName] ?? $fallbackMeta;
                            $icon = $esp->icono ?? $meta['icon'];
                            $serviceImagePath = $siteSettings->get("services.specialty_image.{$esp->id}", $meta['image_path']);
                            $serviceImage = $imageUrl->variants($serviceImagePath, 'services', 'banner', 'public_card');
                        @endphp

                        <article class="card flex h-full min-w-0 flex-col overflow-hidden rounded-[1.5rem] border border-gray-200/80 bg-white shadow-[0_18px_44px_rgba(15,23,42,0.08)]">
                            <div class="h-44 w-full overflow-hidden rounded-t-[1.5rem] border-b border-gray-100 sm:h-48 lg:h-52">
                                <img
                                    src="{{ $serviceImage['thumb'] }}"
                                    @if($serviceImage['srcset']) srcset="{{ $serviceImage['srcset'] }}" sizes="(min-width: 1280px) 28vw, (min-width: 768px) 44vw, 100vw" @endif
                                    alt="Imagen referencial de {{ $esp->nombre }}"
                                    class="h-full w-full rounded-t-[1.5rem] {{ $meta['image_class'] }}"
                                    loading="lazy"
                                    decoding="async"
                                >
                            </div>

                            <div class="flex flex-1 min-w-0 flex-col p-4 sm:p-[1.15rem]">
                                <div class="flex items-start justify-between gap-4">
                                    <div class="inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-2xl bg-gray-100 text-gray-600">
                                        <i class="{{ $icon }}" aria-hidden="true"></i>
                                    </div>
                                    <span class="badge neutral">{{ $meta['tag_label'] }}</span>
                                </div>

                                <h3 class="mt-3.5 text-lg font-semibold text-gray-900">{{ $esp->nombre }}</h3>
                                <p class="mt-2 min-h-[4.5rem] line-clamp-3 text-sm leading-6 text-gray-500">
                                    {{ $esp->descripcion ?: ($meta['descripcion'] ?? '') }}
                                </p>

                                <div class="mt-3.5 flex flex-wrap gap-2">
                                    <span class="badge info">{{ $meta['badge'] }}</span>
                                    <span class="badge neutral">{{ $meta['badge2'] }}</span>
                                </div>

                                <div class="mt-4">
                                    @auth
                                        <a href="{{ route('paciente.crear-cita', ['especialidad' => $esp->id]) }}" class="btn btn-outline w-full">
                                            Agendar
                                        </a>
                                    @else
                                        <a href="{{ url('/') . '?login=1' }}" class="btn btn-outline w-full" data-login-trigger>
                                            Agendar
                                        </a>
                                    @endauth
                                </div>
                            </div>
                        </article>
                    @empty
                        <div class="md:col-span-2 xl:col-span-3">
                            <x-ui.empty-state title="No hay servicios disponibles para la busqueda realizada." message="Ajusta el texto o el tipo de servicio para consultar nuevamente." />
                        </div>
                    @endforelse
                </div>

                @if(method_exists($especialidades, 'links'))
                    <div class="mt-6 flex justify-center">
                        {{ $especialidades->links() }}
                    </div>
                @endif

                <div class="rounded-[1.75rem] border border-gray-200/80 bg-white/95 px-6 py-8 text-center shadow-[0_20px_50px_rgba(15,23,42,0.08)]">
                    <p class="text-sm text-gray-500">Selecciona la especialidad que necesitas y continua con el flujo de agendamiento actual.</p>

                    <div class="mt-4 flex justify-center">
                        @auth
                            <a href="{{ route('paciente.crear-cita') }}" class="btn btn-primary">
                                <i class="ri-calendar-check-line" aria-hidden="true"></i>
                                Agendar cita
                            </a>
                        @else
                            <a href="{{ url('/') . '?login=1' }}" class="btn btn-primary" data-login-trigger>
                                <i class="ri-login-circle-line" aria-hidden="true"></i>
                                Ingresar para agendar
                            </a>
                        @endauth
                    </div>

                    <p class="mt-4 text-sm text-gray-500">Tambien puedes iniciar el agendamiento con el asistente virtual.</p>

                    <div class="mt-4 flex justify-center">
                        <button
                            type="button"
                            class="btn btn-outline"
                            data-chatbot-open
                        >
                            <i class="ri-customer-service-2-line" aria-hidden="true"></i>
                            Abrir asistente virtual
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @include('partials.footer')
</div>
@endsection
