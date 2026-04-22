@php
    // Scope: solo paneles internos autenticados, nunca vistas publicas.
    $panelTheme = auth()->user()?->theme_preference === 'dark' ? 'dark' : 'light';
    $panelThemeUserId = auth()->id();
@endphp

<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="panel-theme-initial" content="{{ $panelTheme }}">
<meta name="panel-theme-user-id" content="{{ $panelThemeUserId }}">
<meta name="panel-theme-session-id" content="{{ session()->getId() }}">
<meta name="panel-theme-update-url" content="{{ route('panel.theme.update') }}">

<script>
    (function () {
        var root = document.documentElement;
        var initial = (document.querySelector('meta[name="panel-theme-initial"]')?.content || 'light').toLowerCase();
        var userId = document.querySelector('meta[name="panel-theme-user-id"]')?.content || '';
        var sessionId = document.querySelector('meta[name="panel-theme-session-id"]')?.content || '';
        var storageKey = userId && sessionId ? 'panel-theme-temp:' + userId + ':' + sessionId : '';
        var temporary = '';

        try {
            temporary = storageKey ? (localStorage.getItem(storageKey) || '').toLowerCase() : '';
        } catch (error) {
            temporary = '';
        }

        var theme = temporary === 'dark' || temporary === 'light'
            ? temporary
            : (initial === 'dark' ? 'dark' : 'light');

        root.classList.remove('panel-theme-light', 'panel-theme-dark');
        root.classList.add(theme === 'dark' ? 'panel-theme-dark' : 'panel-theme-light');
        root.setAttribute('data-panel-theme', theme);
        root.style.colorScheme = theme;
    })();
</script>
