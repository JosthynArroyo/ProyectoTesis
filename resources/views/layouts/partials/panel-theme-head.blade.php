@php
    // Scope: solo paneles internos autenticados, nunca vistas publicas.
    $panelTheme = auth()->user()?->theme_preference === 'dark' ? 'dark' : 'light';
    $panelThemeUserId = auth()->id();
@endphp

<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="panel-theme-initial" content="{{ $panelTheme }}">
<meta name="panel-theme-user-id" content="{{ $panelThemeUserId }}">
<meta name="panel-theme-update-url" content="{{ route('panel.theme.update') }}">

<script>
    (function () {
        var root = document.documentElement;
        var initial = (document.querySelector('meta[name="panel-theme-initial"]')?.content || 'light').toLowerCase();
        var theme = initial === 'dark' ? 'dark' : 'light';

        root.classList.remove('panel-theme-light', 'panel-theme-dark');
        root.classList.add(theme === 'dark' ? 'panel-theme-dark' : 'panel-theme-light');
        root.setAttribute('data-panel-theme', theme);
        root.style.colorScheme = theme;
    })();
</script>
