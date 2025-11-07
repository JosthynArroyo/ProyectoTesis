{{-- resources/views/admin/users/usuarios-pdf.blade.php --}}
<!doctype html>
<html>
<head>
  <meta charset="utf-8">
  <style>
    /* Página y tipografía */
    @page { size: A4 landscape; margin: 18px 22px; }
    body{ font-family: DejaVu Sans, Arial, sans-serif; font-size:11px; color:#0f172a; margin:0; }
    h3{ margin:0 0 10px 0; }

    /* Tabla compacta y de ancho fijo */
    table{ width:100%; border-collapse:collapse; table-layout:fixed; }
    colgroup col{ width:auto; } /* fallback dompdf */
    th,td{
      border:1px solid #d1d5db; padding:5px 6px; vertical-align:top; line-height:1.25;
      word-break:break-word; overflow-wrap:anywhere; white-space:normal;
    }
    th{ background:#f3f4f6; font-weight:700; text-transform:uppercase; font-size:10px; }
    .center{ text-align:center; }
    .nowrap{ white-space:nowrap; } /* úsala si no quieres cortar fechas */
  </style>
</head>
<body>
<h3>Reporte de Usuarios</h3>

<table>
  <!-- Anchos por columna (suman ~100%) -->
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
      <th>Email</th>
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
      <td>{{ $u->status ?? 'active' }}</td>
      <td>{{ optional($u->suspended_until)->format('Y-m-d H:i') }}</td>
      <td>{{ optional($u->created_at)->format('Y-m-d H:i') }}</td>
      <td>{{ optional($u->especialidades)->pluck('nombre')->implode(', ') }}</td>
    </tr>
  @endforeach
  </tbody>
</table>
</body>
</html>
