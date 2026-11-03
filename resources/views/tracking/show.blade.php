@php
    $current = $statuses[$tracking['status']] ?? ['label' => $tracking['status'], 'color' => '#64748b'];
    $fmt = fn (string $iso) => \Carbon\Carbon::parse($iso)->timezone(config('app.timezone'))->locale('es')->translatedFormat('d M · h:i a');
    $vehicles = ['bicycle' => 'Bicicleta', 'motorcycle' => 'Moto', 'car' => 'Carro', 'van' => 'Camioneta', 'truck' => 'Camión', 'on_foot' => 'A pie'];
    $carrier = $tracking['carrier'] ?? null;
    $here = $tracking['approximate_location'];
    $dest = $tracking['destination_point'] ?? null;
    $steps = ['created', 'assigned', 'in_route', 'delivered'];
    $reached = array_column($tracking['timeline'], 'status');
    $money = fn (int $v) => '$'.number_format($v, 0, ',', '.');
@endphp
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>Pedido {{ $tracking['tracking_code'] }} · {{ $branding['name'] }}</title>
    @if ($here || $dest)
        @vite(['resources/js/tracking.ts'])
    @endif
    <style>
        /* Colores corporativos ISA: azul profundo, verde menta (en camino / confirmaciones) y ámbar (alertas y tarifas). */
        :root {
            --navy: {{ $branding['primary_color'] }};
            --mint: {{ $branding['secondary_color'] }};
            --amber: {{ $highlight ?? '#FFB800' }};
            --status: {{ $current['color'] }};
            --bg: #f3f6fa; --card: #ffffff; --text: #0B1F3A; --muted: #5b6b82; --line: #dfe6ef;
        }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #06111f; --card: #0d1d33; --text: #eef3fa; --muted: #93a4bd; --line: #1c2f4a; }
        }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; background: var(--bg); color: var(--text); }
        header { background: var(--navy); color: #fff; padding: 16px; display: flex; align-items: center; gap: 12px; }
        header img { height: 36px; border-radius: 6px; background: #fff; padding: 2px; }
        header h1 { font-size: 1.05rem; margin: 0; }
        header .dot { width: 10px; height: 10px; border-radius: 50%; background: var(--mint); margin-left: auto; box-shadow: 0 0 0 4px rgb(0 200 150 / .25); }
        main { max-width: 560px; margin: 0 auto; padding: 16px; }
        .card { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 18px; margin-bottom: 14px; }
        .code { color: var(--muted); font-size: .8rem; letter-spacing: .06em; }
        .hello { font-size: 1.3rem; margin: 6px 0 10px; font-weight: 700; }
        .badge { display: inline-block; padding: 6px 14px; border-radius: 999px; background: var(--status); color: #0B1F3A; font-weight: 700; }
        .progress { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 16px; }
        .progress span { height: 6px; border-radius: 99px; background: var(--line); }
        .progress span.on { background: var(--mint); }
        .progress-labels { display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px; margin-top: 6px; font-size: .72rem; color: var(--muted); }
        .rider { display: flex; gap: 14px; align-items: center; }
        .rider img, .rider .avatar { width: 64px; height: 64px; border-radius: 50%; object-fit: cover; border: 3px solid var(--mint); flex: none; }
        .rider .avatar { display: grid; place-items: center; background: var(--navy); color: #fff; font-weight: 800; font-size: 1.4rem; }
        .rider b { font-size: 1.05rem; }
        .plate { display: inline-block; margin-top: 4px; font-family: ui-monospace, Menlo, monospace; font-weight: 700; letter-spacing: .08em; background: var(--amber); color: #0B1F3A; padding: 2px 8px; border-radius: 6px; }
        #map { height: 240px; border-radius: 12px; margin-top: 12px; background: var(--line); }
        dl { display: grid; grid-template-columns: auto 1fr; gap: 8px 14px; margin: 0; font-size: .95rem; }
        dt { color: var(--muted); }
        dd { margin: 0; }
        .fee { color: #0B1F3A; background: color-mix(in srgb, var(--amber) 30%, transparent); padding: 1px 8px; border-radius: 6px; font-weight: 700; }
        @media (prefers-color-scheme: dark) { .fee { color: var(--amber); } }
        ol { list-style: none; margin: 0; padding: 0; }
        li { position: relative; padding: 0 0 18px 26px; border-left: 2px solid var(--line); margin-left: 7px; }
        li:last-child { border-left-color: transparent; padding-bottom: 0; }
        li::before { content: ""; position: absolute; left: -8px; top: 2px; width: 14px; height: 14px; border-radius: 50%; background: var(--dot); border: 2px solid var(--card); }
        li strong { display: block; }
        li small { color: var(--muted); }
        .alert { border-left: 4px solid var(--amber); }
        footer { text-align: center; color: var(--muted); font-size: .8rem; padding: 18px; }
    </style>
</head>
<body>
<header>
    @if ($branding['logo_url'])
        <img src="{{ $branding['logo_url'] }}" alt="{{ $branding['name'] }}">
    @endif
    <h1>{{ $branding['name'] }}</h1>
    @if ($tracking['status'] === 'in_route')<span class="dot" aria-hidden="true"></span>@endif
</header>

<main>
    <section class="card">
        <div class="code">PEDIDO {{ $tracking['tracking_code'] }}</div>
        <p class="hello">Hola {{ $tracking['recipient_first_name'] }} 👋</p>
        <span class="badge">{{ $current['label'] }}</span>
        @if (! in_array($tracking['status'], ['incident', 'returned'], true))
            <div class="progress" aria-hidden="true">
                @foreach ($steps as $s)<span class="{{ in_array($s, $reached, true) ? 'on' : '' }}"></span>@endforeach
            </div>
            <div class="progress-labels" aria-hidden="true"><span>Recibido</span><span>Asignado</span><span>En camino</span><span>Entregado</span></div>
        @endif
    </section>

    @if ($tracking['status'] === 'incident')
        @php $last = collect($tracking['timeline'])->last(); @endphp
        <section class="card alert">
            <strong>Tuvimos una novedad</strong>
            <p style="margin:6px 0 0">{{ $incidentReasons[$last['incident_reason'] ?? 'other'] ?? 'Novedad en la entrega' }}. Te contactaremos para coordinar.</p>
        </section>
    @endif

    @if ($carrier)
        <section class="card">
            <div class="rider">
                @if ($carrier['has_photo'])
                    <img src="{{ url('/api/public/tracking/'.$tracking['tracking_code'].'/carrier-photo') }}" alt="Foto de {{ $carrier['first_name'] }}">
                @else
                    <span class="avatar" aria-hidden="true">{{ mb_substr($carrier['first_name'], 0, 1) }}</span>
                @endif
                <div>
                    <small style="color:var(--muted)">Te lo lleva</small><br>
                    <b>{{ $carrier['first_name'] }}</b>
                    @if ($carrier['vehicle_type']) · {{ $vehicles[$carrier['vehicle_type']] ?? $carrier['vehicle_type'] }}@endif
                    @if ($carrier['vehicle_plate'])<br><span class="plate">{{ $carrier['vehicle_plate'] }}</span>@endif
                </div>
            </div>
            @if ($here || $dest)<div id="map" role="img" aria-label="Mapa con la ubicación aproximada del mensajero y tu barrio"></div>@endif
        </section>
    @endif

    <section class="card">
        <dl>
            <dt>Destino</dt><dd>{{ implode(', ', $tracking['destination']) }}</dd>
            <dt>Paquetes</dt><dd>{{ $tracking['packages_count'] }}</dd>
            @if ($tracking['zone_name'] ?? null)
                <dt>Zona</dt><dd>{{ $tracking['zone_name'] }}</dd>
            @endif
            @if (($tracking['delivery_fee'] ?? null) !== null)
                <dt>Domicilio</dt><dd><span class="fee">{{ $money($tracking['delivery_fee']) }}</span></dd>
            @endif
            @if ($tracking['promised_at'])
                <dt>Entrega estimada</dt><dd>{{ $fmt($tracking['promised_at']) }}</dd>
            @endif
            @if ($tracking['received_by'])
                <dt>Recibido por</dt><dd>{{ $tracking['received_by'] }}</dd>
            @endif
        </dl>
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
<footer>Rastreo por {{ config('app.name') }} · Tus datos están protegidos (Ley 1581 de 2012)</footer>

@if ($carrier && ($here || $dest))
    {{-- Datos del mapa para resources/js/tracking.ts (sin script en línea: la CSP lo prohíbe). --}}
    @php
        $mapData = [
            'here' => $here ? [$here['latitude'], $here['longitude']] : null,
            'dest' => $dest ? [$dest['latitude'], $dest['longitude']] : null,
            'style' => $mapStyle,
            'maptilerKey' => $mapStyle === 'maptiler' ? $maptilerKey : null,
        ];
    @endphp
    <script type="application/json" id="tracking-map-data">@json($mapData)</script>
@endif
</body>
</html>
