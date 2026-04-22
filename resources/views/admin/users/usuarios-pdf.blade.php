{{-- resources/views/admin/users/usuarios-pdf.blade.php --}}
<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <style>{{ $pdfCss }}</style>
</head>
<body>
<h3>Reporte de usuarios</h3>

<table>
  <colgroup>
    <col style="width:5%">
    <col style="width:14%">
    <col style="width:16%">
    <col style="width:10%">
    <col style="width:10%">
    <col style="width:7%">
    <col style="width:6%">
    <col style="width:10%">
    <col style="width:10%">
    <col style="width:12%">
  </colgroup>

  <thead>
    <tr>
      <th>ID</th>
      <th>Nombre</th>
      <th>Correo electrónico</th>
      <th>Teléfono</th>
      <th>Cédula</th>
      <th>Rol</th>
      <th>Estado</th>
      <th>Suspendido</th>
      <th>Creado</th>
      <th>Especialidades</th>
    </tr>
  </thead>

  <tbody>
  @foreach($users as $u)
    <tr>
      <td class="center">{{ $u->id }}</td>
      <td>{{ $u->name }}</td>
      <td>{{ $u->email }}</td>
      <td>{{ $u->telefono }}</td>
      <td>{{ $u->dni }}</td>
      <td>{{ optional($u->roles->first())->name }}</td>
      <td>{{ $statusLabels[$u->status ?? 'active'] ?? 'Activo' }}</td>
      <td>{{ optional($u->suspended_until)->format('Y-m-d H:i') }}</td>
      <td>{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>
      <td>{{ optional($u->especialidades)->pluck('nombre')->implode(', ') }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
</body>
</html>
