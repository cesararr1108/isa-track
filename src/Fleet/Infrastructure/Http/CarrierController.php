<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Isa\Fleet\Application\CarrierPresenter;
use Isa\Fleet\Application\ChangeCarrierPhoto\ChangeCarrierPhotoHandler;
use Isa\Fleet\Application\GetCarrier\GetCarrierHandler;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Fleet\Application\RegisterCarrier\RegisterCarrierHandler;
use Isa\Fleet\Application\SetCarrierActive\SetCarrierActiveHandler;
use Isa\Fleet\Application\UpdateCarrier\UpdateCarrierHandler;
use Isa\Fleet\Domain\Exception\CarrierNotFound;
use Isa\Shared\Infrastructure\Auth\DriverScope;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Transportadores / mensajeros: datos, vehículo, capacidad y foto. */
final class CarrierController
{
    public function index(Request $request, ListCarriersHandler $listCarriers): JsonResponse
    {
        return new JsonResponse(['data' => $listCarriers($request->boolean('only_active'))]);
    }

    public function show(string $carrier, GetCarrierHandler $getCarrier): JsonResponse
    {
        return new JsonResponse(['data' => CarrierPresenter::toArray($getCarrier($carrier))]);
    }

    public function store(CarrierRequest $request, RegisterCarrierHandler $registerCarrier, GetCarrierHandler $getCarrier): JsonResponse
    {
        $id = $registerCarrier($request->toData());

        return new JsonResponse(['id' => $id->value(), 'data' => CarrierPresenter::toArray($getCarrier($id->value()))], 201);
    }

    public function update(CarrierRequest $request, string $carrier, UpdateCarrierHandler $updateCarrier): JsonResponse
    {
        return new JsonResponse(['data' => CarrierPresenter::toArray($updateCarrier($carrier, $request->toData()))]);
    }

    public function setActive(Request $request, string $carrier, SetCarrierActiveHandler $setActive): JsonResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);

        return new JsonResponse(['data' => CarrierPresenter::toArray($setActive($carrier, (bool) $data['active']))]);
    }

    /** POST /api/carriers/{id}/photo (multipart: photo) */
    public function uploadPhoto(CarrierPhotoRequest $request, string $carrier, ChangeCarrierPhotoHandler $changePhoto): JsonResponse
    {
        $photo = $request->photo();

        return new JsonResponse(['data' => CarrierPresenter::toArray(
            $changePhoto($carrier, $photo->getRealPath(), $photo->extension() ?: 'jpg'),
        )]);
    }

    /** POST /api/me/photo: el mensajero sube su propia foto desde la app. */
    public function uploadMyPhoto(CarrierPhotoRequest $request, ChangeCarrierPhotoHandler $changePhoto): JsonResponse
    {
        $carrierId = DriverScope::carrierId($request) ?? $request->user()->carrier_id;
        abort_if($carrierId === null, 422, 'Tu usuario no está vinculado a un transportador.');

        return $this->uploadPhoto($request, $carrierId, $changePhoto);
    }

    /** GET /api/carriers/{id}/photo */
    public function photo(string $carrier, GetCarrierHandler $getCarrier): StreamedResponse
    {
        $path = $getCarrier($carrier)->photoPath() ?? throw new CarrierNotFound($carrier);

        return Storage::disk(config('isa.fleet.photo_disk'))->response($path);
    }
}
