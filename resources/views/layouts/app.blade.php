{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="csrf-token" content="{{ csrf_token() }}">
  <title>@yield('title','Clínica')</title>
  @include('layouts.partials.favicon')
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2family=Sora:wght@300;400;500;600;700&family=Space+Grotesk:wght@500;600;700&display=swap">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/remixicon/4.2.0/remixicon.min.css">

  @vite(['resources/css/app.css','resources/js/app.js'])

  @stack('styles')
</head>
<body class="min-h-screen text-slate-900">
  @yield('content')

  @guest
    @include('chatbot.widget')
  @endguest

  @stack('scripts')
</body>
</html>