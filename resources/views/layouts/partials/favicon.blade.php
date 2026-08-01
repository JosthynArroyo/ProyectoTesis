@php
  try {
      $identity = (isset($clinicIdentity) && $clinicIdentity instanceof \App\Services\ClinicIdentityService)
          ? $clinicIdentity
          : app(\App\Services\ClinicIdentityService::class);
      $faviconUrl = $identity->faviconUrl();
  } catch (\Throwable $e) {
      $faviconUrl = asset('img/placeholders/default.svg');
  }

  $faviconExt = strtolower(pathinfo(parse_url($faviconUrl, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
  $faviconType = match ($faviconExt) {
      'png' => 'image/png',
      'webp' => 'image/webp',
      'svg' => 'image/svg+xml',
      'jpg', 'jpeg' => 'image/jpeg',
      default => null,
  };
@endphp

<link rel="icon" @if($faviconType) type="{{ $faviconType }}" @endif href="{{ $faviconUrl }}">
<link rel="shortcut icon" @if($faviconType) type="{{ $faviconType }}" @endif href="{{ $faviconUrl }}">
<link rel="apple-touch-icon" href="{{ $faviconUrl }}">
