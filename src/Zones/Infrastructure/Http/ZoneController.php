<?php

declare(strict_types=1);

namespace Isa\Zones\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Isa\Shared\Application\TenantContext;
use Isa\Zones\Application\DeleteZone\DeleteZoneHandler;
use Isa\Zones\Application\ListZones\ListZonesHandler;
use Isa\Zones\Application\QuoteDeliveryFee\QuoteDeliveryFeeHandler;
use Isa\Zones\Application\SaveZone\SaveZoneCommand;
use Isa\Zones\Application\SaveZone\SaveZoneHandler;
use Isa\Zones\Application\ZonePresenter;

/** Zonas de cobertura y precios de domicilio por zona. */
final class ZoneController
{
    public function index(Request $request, ListZonesHandler $listZones): JsonResponse
    {
        return new JsonResponse(['data' => $listZones($request->boolean('only_active'))]);
    }

    public function store(Request $request, SaveZoneHandler $saveZone): JsonResponse
    {
        return new JsonResponse(['data' => ZonePresenter::toArray($saveZone($this->command($request)))], 201);
    }

    public function update(Request $request, string $zone, SaveZoneHandler $saveZone): JsonResponse
    {
        return new JsonResponse(['data' => ZonePresenter::toArray($saveZone($this->command($request, $zone)))]);
    }

    public function destroy(string $zone, DeleteZoneHandler $deleteZone): JsonResponse
    {
        $deleteZone($zone);

        return new JsonResponse(status: 204);
    }

    /** GET /api/zones/quote?latitude=&longitude= */
    public function quote(Request $request, QuoteDeliveryFeeHandler $quote, TenantContext $tenant): JsonResponse
    {
        $data = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return new JsonResponse(['data' => $quote($tenant->companyId(), (float) $data['latitude'], (float) $data['longitude'])]);
    }

    private function command(Request $request, ?string $zoneId = null): SaveZoneCommand
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'delivery_fee' => ['required', 'integer', 'min:0', 'max:10000000'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'active' => ['nullable', 'boolean'],
            'polygon' => ['required', 'array', 'min:3', 'max:500'],
            'polygon.*' => ['required', 'array', 'size:2'],
            'polygon.*.0' => ['required', 'numeric', 'between:-90,90'],
            'polygon.*.1' => ['required', 'numeric', 'between:-180,180'],
        ]);

        return new SaveZoneCommand(
            name: $data['name'],
            color: $data['color'] ?? '#00C896',
            polygon: array_map(fn (array $p) => [(float) $p[0], (float) $p[1]], $data['polygon']),
            deliveryFee: (int) $data['delivery_fee'],
            estimatedMinutes: isset($data['estimated_minutes']) ? (int) $data['estimated_minutes'] : null,
            active: $request->has('active') ? $request->boolean('active') : true,
            zoneId: $zoneId,
        );
    }
}
