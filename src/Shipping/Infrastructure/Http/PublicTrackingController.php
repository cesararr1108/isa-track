<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Http;

use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Isa\Shipping\Application\TrackOrder\TrackOrderHandler;
use Isa\Shipping\Domain\Exception\OrderNotFound;

final class PublicTrackingController
{
    public function json(string $code, TrackOrderHandler $trackOrder): JsonResponse
    {
        return new JsonResponse(['data' => $trackOrder($code)]);
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
        ]);
    }
}
