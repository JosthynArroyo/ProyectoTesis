@php($scope = $scope ?? 'admin')

<nav class="card p-3">
  <div class="flex flex-wrap gap-2">
    <a href="{{ route($scope.'.personalizacion.bienvenida.edit') }}" class="btn {{ request()->routeIs($scope.'.personalizacion.bienvenida.*') ? 'btn-primary' : 'btn-outline' }}">
      Bienvenida
    </a>
    <a href="{{ route($scope.'.personalizacion.servicios.edit') }}" class="btn {{ request()->routeIs($scope.'.personalizacion.servicios.*') ? 'btn-primary' : 'btn-outline' }}">
      Servicios
    </a>
    <a href="{{ route($scope.'.personalizacion.contacto.edit') }}" class="btn {{ request()->routeIs($scope.'.personalizacion.contacto.*') ? 'btn-primary' : 'btn-outline' }}">
      Contacto
    </a>
  </div>
</nav>
