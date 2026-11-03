<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

/**
 * Encabezados de seguridad para todas las páginas web.
 * Con el parámetro "csp" (->middleware('security.headers:csp')) agrega además
 * una Content-Security-Policy estricta: solo scripts propios firmados con nonce
 * y conexiones a los proveedores de mapas configurados.
 */
final class SecurityHeaders
{
    /** Proveedores de mapas permitidos (teselas en imágenes y estilos vectoriales). */
    private const MAP_IMG = ['https://tile.openstreetmap.org', 'https://server.arcgisonline.com', 'https://tiles.openfreemap.org', 'https://api.maptiler.com'];

    private const MAP_CONNECT = ['https://tiles.openfreemap.org', 'https://api.maptiler.com'];

    public function handle(Request $request, Closure $next, ?string $mode = null): Response
    {
        if ($mode === 'csp') {
            Vite::useCspNonce();
        }

        /** @var Response $response */
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        // Enviar solo el origen a otros sitios (los mapas lo necesitan; nunca la ruta completa).
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'geolocation=(self), camera=(self), microphone=(), payment=(), usb=()');
        $h->set('Cross-Origin-Opener-Policy', 'same-origin');
        if ($request->isSecure()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        if ($mode === 'csp') {
            $h->set('Content-Security-Policy', $this->csp());
        }

        return $response;
    }

    private function csp(): string
    {
        $nonce = Vite::cspNonce();
        $dev = [];
        // En desarrollo (npm run dev) el código viene del servidor de Vite.
        if (is_file(public_path('hot'))) {
            $hot = rtrim((string) file_get_contents(public_path('hot')));
            $dev = [$hot, preg_replace('#^http#', 'ws', $hot)];
        }

        $directives = [
            'default-src' => ["'self'"],
            'script-src' => ["'self'", "'nonce-{$nonce}'", ...$dev],
            // Leaflet y Vue posicionan elementos con atributos style.
            'style-src' => ["'self'", "'unsafe-inline'", ...$dev],
            'img-src' => ["'self'", 'data:', 'blob:', ...self::MAP_IMG],
            'font-src' => ["'self'", 'data:'],
            'connect-src' => ["'self'", ...self::MAP_CONNECT, ...$dev],
            'worker-src' => ["'self'", 'blob:'],
            'object-src' => ["'none'"],
            'base-uri' => ["'self'"],
            'form-action' => ["'self'"],
            'frame-ancestors' => ["'none'"],
        ];

        return implode('; ', array_map(fn ($k, $v) => $k.' '.implode(' ', $v), array_keys($directives), $directives));
    }
}
