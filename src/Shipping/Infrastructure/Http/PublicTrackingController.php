<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Isa\Shipping\Application\TrackOrder\TrackOrderHandler;
use Isa\Shipping\Domain\Exception\OrderNotFound;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PublicTrackingController
{
    public function json(string $code, TrackOrderHandler $trackOrder): JsonResponse
    {
        return new JsonResponse(['data' => $trackOrder($code)]);
    }

    /** Foto del mensajero para la página del cliente (solo mientras va en camino). */
    public function carrierPhoto(string $code, TrackOrderHandler $trackOrder): StreamedResponse
    {
        try {
            $path = $trackOrder->carrierPhotoPath($code);
        } catch (OrderNotFound) {
            abort(404);
        }
        abort_if($path === null, 404);

        return Storage::disk(config('isa.fleet.photo_disk'))->response($path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    public function page(string $code, TrackOrderHandler $trackOrder): View
    {
        try {
            $tracking = $trackOrder($code);
        } catch (OrderNotFound) {
            abort(404);
        }

        return view('tracking.show', [
            'tracking' => $tracking,
            'statuses' => config('isa.statuses'),
            'incidentReasons' => config('isa.incident_reasons'),
            'branding' => $tracking['company'] ?? [
                'name' => config('app.name'),
                'logo_url' => null,
                'primary_color' => config('isa.branding.primary_color'),
                'secondary_color' => config('isa.branding.secondary_color'),
            ],
            'highlight' => config('isa.branding.highlight_color'),
            'depot' => config('isa.routing.depot'),
            'mapStyleUrl' => self::mapStyleUrl(),
        ]);
    }

    /** URL del estilo vectorial del mapa, o null para usar OpenStreetMap en imágenes. */
    private static function mapStyleUrl(): ?string
    {
        $style = (string) config('isa.maps.style');
        $key = config('isa.maps.maptiler_key');
        if ($style === 'maptiler') {
            return $key ? 'https://api.maptiler.com/maps/streets-v2/style.json?key='.urlencode((string) $key) : null;
        }
        $openFreeMap = ['urbano' => 'liberty', 'claro' => 'positron', 'brillante' => 'bright'][$style] ?? null;

        return $openFreeMap === null ? null : 'https://tiles.openfreemap.org/styles/'.$openFreeMap;
    }
}
