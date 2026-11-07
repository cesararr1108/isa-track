<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0B1F3A">
    @if (request()->is('mensajero', 'mensajero/*'))
        {{-- App del mensajero: se puede instalar en el celular (PWA). --}}
        <link rel="manifest" href="/mensajero-app/manifest.json">
        <link rel="apple-touch-icon" href="/mensajero-app/apple-touch-icon.png">
        <meta name="apple-mobile-web-app-capable" content="yes">
        <meta name="mobile-web-app-capable" content="yes">
        <meta name="apple-mobile-web-app-title" content="ISA Mensajero">
    @endif
    <title inertia>{{ config('app.name') }}</title>
    @vite(['resources/js/app.ts'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
