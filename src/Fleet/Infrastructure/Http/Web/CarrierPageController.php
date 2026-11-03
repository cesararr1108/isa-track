<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure\Http\Web;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Fleet\Application\ChangeCarrierPhoto\ChangeCarrierPhotoHandler;
use Isa\Fleet\Application\GetCarrier\GetCarrierHandler;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Fleet\Application\RegisterCarrier\RegisterCarrierHandler;
use Isa\Fleet\Application\SetCarrierActive\SetCarrierActiveHandler;
use Isa\Fleet\Application\UpdateCarrier\UpdateCarrierHandler;
use Isa\Fleet\Domain\Exception\CarrierNotFound;
use Isa\Fleet\Infrastructure\Http\CarrierPhotoRequest;
use Isa\Fleet\Infrastructure\Http\CarrierRequest;
use Isa\Shared\Application\TenantContext;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Pantalla de transportadores del panel. Usa los mismos casos de uso que la API. */
final class CarrierPageController
{
    public function index(ListCarriersHandler $list, TenantContext $tenant): Response
    {
        $users = User::query()
            ->where('company_id', $tenant->companyId()->value())
            ->whereNotNull('carrier_id')
            ->pluck('email', 'carrier_id');

        return Inertia::render('fleet/pages/CarriersIndex', [
            'carriers' => array_map(fn (array $c) => $c + [
                'photo_url' => $c['has_photo'] ? route('panel.carriers.photo', $c['id']) : null,
                'user_email' => $users[$c['id']] ?? null,
            ], $list()),
            'vehicleTypes' => config('isa.fleet.vehicle_labels'),
        ]);
    }

    public function store(CarrierRequest $request, RegisterCarrierHandler $register, ChangeCarrierPhotoHandler $changePhoto): RedirectResponse
    {
        $this->validatePhoto($request);
        $id = $register($request->toData());
        $this->savePhoto($request, $id->value(), $changePhoto);

        return back()->with('success', 'Transportador registrado.');
    }

    public function update(CarrierRequest $request, string $carrier, UpdateCarrierHandler $update, ChangeCarrierPhotoHandler $changePhoto): RedirectResponse
    {
        $this->validatePhoto($request);
        $update($carrier, $request->toData());
        $this->savePhoto($request, $carrier, $changePhoto);

        return back()->with('success', 'Cambios guardados.');
    }

    public function setActive(Request $request, string $carrier, SetCarrierActiveHandler $setActive): RedirectResponse
    {
        $data = $request->validate(['active' => ['required', 'boolean']]);
        $setActive($carrier, (bool) $data['active']);

        return back()->with('success', $data['active'] ? 'Transportador activado.' : 'Transportador desactivado: ya no se le pueden asignar pedidos.');
    }

    public function photo(string $carrier, GetCarrierHandler $getCarrier): StreamedResponse
    {
        $path = $getCarrier($carrier)->photoPath() ?? throw new CarrierNotFound($carrier);

        return Storage::disk(config('isa.fleet.photo_disk'))->response($path, null, ['Cache-Control' => 'private, max-age=300']);
    }

    /** La foto se valida ANTES de guardar los datos, para no dejar cambios a medias. */
    private function validatePhoto(Request $request): void
    {
        if ($request->hasFile('photo')) {
            $request->validate(['photo' => (new CarrierPhotoRequest)->rules()['photo']], [], ['photo' => 'foto']);
        }
    }

    private function savePhoto(Request $request, string $carrierId, ChangeCarrierPhotoHandler $changePhoto): void
    {
        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $changePhoto($carrierId, $photo->getRealPath(), $photo->extension() ?: 'jpg');
        }
    }
}
