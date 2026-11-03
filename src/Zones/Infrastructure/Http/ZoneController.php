<?php

declare(strict_types=1);

namespace Isa\Zones\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Isa\Shared\Application\TenantContext;
use Isa\Zones\Application\DeleteZone\DeleteZoneHandler;
use Isa\Zones\Application\ListZones\ListZonesHandler;
use Isa\Zones\Application\QuoteDeliveryFee\QuoteDeliveryFeeHandler;
use Isa\Zones\Application\SaveZone\SaveZoneHandler;
use Isa\Zones\Application\ZonePresenter;

/** Zonas de cobertura y precios de domicilio por zona. */
final class ZoneController
{
    public function index(Request $request, ListZonesHandler $listZones): JsonResponse
    {
        return new JsonResponse(['data' => $listZones($request->boolean('only_active'))]);
    }

    public function store(ZoneRequest $request, SaveZoneHandler $saveZone): JsonResponse
    {
        return new JsonResponse(['data' => ZonePresenter::toArray($saveZone($request->toCommand()))], 201);
    }

    public function update(ZoneRequest $request, string $zone, SaveZoneHandler $saveZone): JsonResponse
    {
        return new JsonResponse(['data' => ZonePresenter::toArray($saveZone($request->toCommand($zone)))]);
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
}
