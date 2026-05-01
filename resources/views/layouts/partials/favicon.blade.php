@php
  $faviconUrl = isset($clinicIdentity)
    ? $clinicIdentity->faviconUrl()
    : asset('img/placeholders/default.svg');
@endphp

<link rel="icon" href="{{ $faviconUrl }}">
<link rel="shortcut icon" href="{{ $faviconUrl }}">
<link rel="apple-touch-icon" href="{{ $faviconUrl }}">
