<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Isa\Fleet\Application\CarrierData;
use Isa\Fleet\Application\CarrierPresenter;
use Isa\Fleet\Application\ChangeCarrierPhoto\ChangeCarrierPhotoHandler;
use Isa\Fleet\Application\GetCarrier\GetCarrierHandler;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Fleet\Application\RegisterCarrier\RegisterCarrierHandler;
use Isa\Fleet\Application\SetCarrierActive\SetCarrierActiveHandler;
use Isa\Fleet\Application\UpdateCarrier\UpdateCarrierHandler;
use Isa\Fleet\Domain\CarrierType;
use Isa\Fleet\Domain\Exception\CarrierNotFound;
use Isa\Fleet\Domain\VehicleType;
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

    public function store(Request $request, RegisterCarrierHandler $registerCarrier, GetCarrierHandler $getCarrier): JsonResponse
    {
        $id = $registerCarrier($this->carrierData($request));

        return new JsonResponse(['id' => $id->value(), 'data' => CarrierPresenter::toArray($getCarrier($id->value()))], 201);
    }

    public function update(Request $request, string $carrier, UpdateCarrierHandler $updateCarrier): JsonResponse
    {
        return new JsonResponse(['data' => CarrierPresenter::toArray($updateCarrier($carrier, $this->carrierData($request)))]);
    }

    public function setActive(Request $request, string $carrier, SetCarrierActiveHandler $setActive): JsonResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);

        return new JsonResponse(['data' => CarrierPresenter::toArray($setActive($carrier, (bool) $data['active']))]);
    }

    /** POST /api/carriers/{id}/photo (multipart: photo) */
    public function uploadPhoto(Request $request, string $carrier, ChangeCarrierPhotoHandler $changePhoto): JsonResponse
    {
        $photo = $this->validatedPhoto($request);

        return new JsonResponse(['data' => CarrierPresenter::toArray(
            $changePhoto($carrier, $photo->getRealPath(), $photo->extension() ?: 'jpg'),
        )]);
    }

    /** POST /api/me/photo: el mensajero sube su propia foto desde la app. */
    public function uploadMyPhoto(Request $request, ChangeCarrierPhotoHandler $changePhoto): JsonResponse
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

    private function validatedPhoto(Request $request): UploadedFile
    {
        $request->validate([
            'photo' => ['required', 'image', 'max:'.(int) config('isa.fleet.photo_max_kb')],
        ]);

        return $request->file('photo');
    }

    private function carrierData(Request $request): CarrierData
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(CarrierType::class)],
            'document_number' => ['nullable', 'string', 'max:20'],
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:150'],
            'vehicle_plate' => ['nullable', 'string', 'max:10'],
            'vehicle_type' => ['nullable', Rule::enum(VehicleType::class)],
            'capacity_kg' => ['nullable', 'numeric', 'gt:0', 'max:50000'],
            'capacity_packages' => ['nullable', 'integer', 'min:1', 'max:10000'],
        ]);

        return new CarrierData(
            name: $data['name'],
            type: $data['type'],
            phone: $data['phone'] ?? null,
            vehiclePlate: $data['vehicle_plate'] ?? null,
            documentNumber: $data['document_number'] ?? null,
            vehicleType: $data['vehicle_type'] ?? null,
            capacityKg: isset($data['capacity_kg']) ? (float) $data['capacity_kg'] : null,
            capacityPackages: isset($data['capacity_packages']) ? (int) $data['capacity_packages'] : null,
            email: $data['email'] ?? null,
        );
    }
}
