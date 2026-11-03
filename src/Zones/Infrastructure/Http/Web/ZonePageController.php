<?php

declare(strict_types=1);

namespace Isa\Zones\Infrastructure\Http\Web;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Shared\Application\TenantContext;
use Isa\Zones\Application\DeleteZone\DeleteZoneHandler;
use Isa\Zones\Application\ListZones\ListZonesHandler;
use Isa\Zones\Application\QuoteDeliveryFee\QuoteDeliveryFeeHandler;
use Isa\Zones\Application\SaveZone\SaveZoneHandler;
use Isa\Zones\Domain\Exception\LocationOutsideCoverage;
use Isa\Zones\Infrastructure\Http\ZoneRequest;

/** Pantalla de zonas y tarifas del panel. */
final class ZonePageController
{
    public function index(ListZonesHandler $list): Response
    {
        return Inertia::render('zones/pages/ZonesIndex', ['zones' => $list()]);
    }

    public function store(ZoneRequest $request, SaveZoneHandler $save): RedirectResponse
    {
        $save($request->toCommand());

        return back()->with('success', 'Zona creada. Los pedidos nuevos en esa área toman su tarifa.');
    }

    public function update(ZoneRequest $request, string $zone, SaveZoneHandler $save): RedirectResponse
    {
        $save($request->toCommand($zone));

        return back()->with('success', 'Zona guardada. Los pedidos ya creados conservan su tarifa.');
    }

    public function destroy(string $zone, DeleteZoneHandler $delete): RedirectResponse
    {
        $delete($zone);

        return back()->with('success', 'Zona eliminada.');
    }

    /** GET /zonas/cotizar?latitude=&longitude=  (JSON, misma sesión del panel) */
    public function quote(Request $request, QuoteDeliveryFeeHandler $quote, TenantContext $tenant): JsonResponse
    {
        $d = $request->validate([
            'latitude' => ['required', 'numeric', 'between:-90,90'],
            'longitude' => ['required', 'numeric', 'between:-180,180'],
        ]);

        try {
            return new JsonResponse(['data' => $quote($tenant->companyId(), (float) $d['latitude'], (float) $d['longitude'])]);
        } catch (LocationOutsideCoverage $e) {
            return new JsonResponse(['data' => null, 'message' => $e->getMessage()]);
        }
    }
}
