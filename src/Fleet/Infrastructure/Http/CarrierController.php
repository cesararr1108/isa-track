<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Fleet\Application\RegisterCarrier\RegisterCarrierCommand;
use Isa\Fleet\Application\RegisterCarrier\RegisterCarrierHandler;
use Isa\Fleet\Domain\CarrierType;

final class CarrierController
{
    public function index(Request $request, ListCarriersHandler $listCarriers): JsonResponse
    {
        return new JsonResponse(['data' => $listCarriers($request->boolean('only_active'))]);
    }

    public function store(Request $request, RegisterCarrierHandler $registerCarrier): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(CarrierType::class)],
            'phone' => ['nullable', 'string', 'max:30'],
            'vehicle_plate' => ['nullable', 'string', 'max:10'],
        ]);

        $id = $registerCarrier(new RegisterCarrierCommand(
            $data['name'],
            $data['type'],
            $data['phone'] ?? null,
            $data['vehicle_plate'] ?? null,
        ));

        return new JsonResponse(['id' => $id->value()], 201);
    }
}
