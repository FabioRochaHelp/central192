<meta charset="utf-8" />
<meta name="viewport" content="width=device-width, initial-scale=1.0" />
<meta name="csrf-token" content="{{ csrf_token() }}" />

<title>
    {{ filled($title ?? null) ? $title.' - '.config('app.name', 'Laravel') : config('app.name', 'Laravel') }}
</title>

<link rel="icon" href="/favicon.ico" sizes="any">
<link rel="icon" href="/favicon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">

<link rel="preconnect" href="https://fonts.bunny.net" crossorigin />
<link
    href="https://fonts.bunny.net/css?family=archivo:wght@400;500;600;700;800;900&family=jetbrains-mono:wght@400;600&display=swap"
    rel="stylesheet"
/>

@fonts

@vite(['resources/css/app.css', 'resources/js/app.js'])

{{-- Primeira visita (sem preferência salva): tema claro. Quem já tem flux.appearance não é alterado. --}}
<script>
    (function () {
        try {
            if (!localStorage.getItem('flux.hasVisited')) {
                localStorage.setItem('flux.hasVisited', '1');
                if (localStorage.getItem('flux.appearance') === null) {
                    localStorage.setItem('flux.appearance', 'light');
                }
            }
        } catch (e) {}
    })();
</script>
@fluxAppearance

@stack('head')
