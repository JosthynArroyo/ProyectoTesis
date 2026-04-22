{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title','Clínica')</title>
  @include('layouts.partials.favicon')
  @include('layouts.partials.fonts')

  @vite(['resources/css/app.css','resources/js/app.js'])

  @stack('styles')
</head>
<body class="min-h-screen text-slate-900">
  @php($maintenanceEnabled = $siteSettings->getBool('maintenance.enabled', false))
  @yield('content')

  @guest
    @unless($maintenanceEnabled)
    @include('chatbot.widget')
    @endunless
  @endguest

  @include('partials.legal-modals')
  @stack('scripts')
</body>
</html>
