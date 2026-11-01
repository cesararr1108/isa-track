<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\ChangeCarrierPhoto;

use Isa\Fleet\Application\GetCarrier\GetCarrierHandler;
use Isa\Fleet\Application\Port\CarrierPhotoStorage;
use Isa\Fleet\Domain\Carrier;
use Isa\Fleet\Domain\CarrierRepository;
use Throwable;

/** Sube (o reemplaza) la foto del mensajero; borra la anterior. */
final class ChangeCarrierPhotoHandler
{
    public function __construct(
        private readonly CarrierRepository $carriers,
        private readonly GetCarrierHandler $getCarrier,
        private readonly CarrierPhotoStorage $storage,
    ) {}

    public function __invoke(string $carrierId, string $localPath, string $extension): Carrier
    {
        $carrier = ($this->getCarrier)($carrierId);
        $path = $this->storage->store($carrier->companyId(), $carrier->id(), $localPath, $extension);

        try {
            $previous = $carrier->changePhoto($path);
            $this->carriers->save($carrier);
        } catch (Throwable $e) {
            $this->storage->delete($path);
            throw $e;
        }

        if ($previous !== null) {
            $this->storage->delete($previous);
        }

        return $carrier;
    }
}
