@php
    $current = $statuses[$tracking['status']] ?? ['label' => $tracking['status'], 'color' => '#64748b'];
    $fmt = fn (string $iso) => \Carbon\Carbon::parse($iso)->timezone(config('app.timezone'))->locale('es')->translatedFormat('d M · h:i a');
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Pedido {{ $tracking['tracking_code'] }} · {{ $branding['name'] }}</title>
    <style>
        :root {
            --brand: {{ $branding['primary_color'] }};
            --accent: {{ $branding['secondary_color'] }};
            --status: {{ $current['color'] }};
            --bg: #f8fafc; --card: #ffffff; --text: #0f172a; --muted: #64748b; --line: #e2e8f0;
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #0b1120; --card: #111827; --text: #f1f5f9; --muted: #94a3b8; --line: #1f2937; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); }
        header { background: var(--brand); color: #fff; padding: 16px; display: flex; align-items: center; gap: 12px; }
        header img { height: 36px; border-radius: 6px; background: #fff; padding: 2px; }
        header h1 { font-size: 1.05rem; margin: 0; }
        main { max-width: 560px; margin: 0 auto; padding: 16px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 14px; padding: 18px; margin-bottom: 14px; }
        .code { color: var(--muted); font-size: .85rem; letter-spacing: .04em; }
        .badge { display: inline-block; margin-top: 8px; padding: 6px 12px; border-radius: 999px; background: var(--status); color: #fff; font-weight: 600; }
        .hello { font-size: 1.25rem; margin: 6px 0 0; }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 6px 14px; margin: 0; font-size: .95rem; }
        dt { color: var(--muted); }
        dd { margin: 0; }
        ol { list-style: none; margin: 0; padding: 0; }
        li { position: relative; padding: 0 0 18px 26px; border-left: 2px solid var(--line); margin-left: 7px; }
        li:last-child { border-left-color: transparent; padding-bottom: 0; }
        li::before { content: ""; position: absolute; left: -8px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--dot); border: 2px solid var(--card); }
        li strong { display: block; }
        li small { color: var(--muted); }
        a.map { display: inline-block; margin-top: 10px; color: var(--brand); font-weight: 600; }
        footer { text-align: center; color: var(--muted); font-size: .8rem; padding: 18px; }
    </style>
</head>
<body>
<header>
    @if ($branding['logo_url'])
        <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['name'] }}">
    @endif
    <h1>{{ $branding['name'] }}</h1>
</header>

<main>
    <section class="card">
        <div class="code">PEDIDO {{ $tracking['tracking_code'] }}</div>
        <p class="hello">Hola {{ $tracking['recipient_first_name'] }} 👋</p>
        <span class="badge">{{ $current['label'] }}</span>
    </section>

    <section class="card">
        <dl>
            <dt>Destino</dt><dd>{{ implode(', ', $tracking['destination']) }}</dd>
            <dt>Paquetes</dt><dd>{{ $tracking['packages_count'] }}</dd>
            @if ($tracking['promised_at'])
                <dt>Entrega estimada</dt><dd>{{ $fmt($tracking['promised_at']) }}</dd>
            @endif
            @if ($tracking['carrier_name'])
                <dt>Te lo lleva</dt><dd>{{ $tracking['carrier_name'] }}</dd>
            @endif
        </dl>
        @if ($tracking['approximate_location'])
            <a class="map" target="_blank" rel="noopener"
               href="https://www.openstreetmap.org/?mlat={{ $tracking['approximate_location']['latitude'] }}&mlon={{ $tracking['approximate_location']['longitude'] }}#map=15/{{ $tracking['approximate_location']['latitude'] }}/{{ $tracking['approximate_location']['longitude'] }}">
                Ver ubicación aproximada del conductor →
            </a>
        @endif
    </section>

    <section class="card">
        <ol>
            @foreach (array_reverse($tracking['timeline']) as $step)
                <li style="--dot: {{ $statuses[$step['status']]['color'] ?? '#64748b' }}">
                    <strong>{{ $statuses[$step['status']]['label'] ?? $step['status'] }}</strong>
                    @if ($step['incident_reason'])
                        <span>{{ $incidentReasons[$step['incident_reason']] ?? $step['incident_reason'] }}</span><br>
                    @endif
                    <small>{{ $fmt($step['occurred_at']) }}</small>
                </li>
            @endforeach
        </ol>
    </section>
</main>
<footer>Rastreo por {{ config('app.name') }}</footer>
</body>
</html>
