{{-- resources/views/layouts/app.blade.php --}}
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>@yield('title','Clínica')</title>
  <link rel="icon" type="image/jpg" href="{{ asset('img/LogoClinica.jpg') }}">

  {{-- si usas Vite/Bootstrap, déjalo aquí --}}
  @vite(['resources/css/app.css','resources/js/app.js'])

  {{-- NECESARIO para que entren los estilos de las vistas --}}
  @stack('styles')
</head>
<body>
  @yield('content')

  {{-- NECESARIO para los scripts que se pushean desde las vistas --}}
  @stack('scripts')
</body>
</html>
