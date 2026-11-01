<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\SetCarrierActive;

use Isa\Fleet\Application\GetCarrier\GetCarrierHandler;
use Isa\Fleet\Domain\Carrier;
use Isa\Fleet\Domain\CarrierRepository;

/** Activa o inactiva un transportador (inactivo = no se le pueden asignar pedidos). */
final class SetCarrierActiveHandler
{
    public function __construct(
        private readonly CarrierRepository $carriers,
        private readonly GetCarrierHandler $getCarrier,
    ) {}

    public function __invoke(string $carrierId, bool $active): Carrier
    {
        $carrier = ($this->getCarrier)($carrierId);
        $active ? $carrier->activate() : $carrier->deactivate();
        $this->carriers->save($carrier);

        return $carrier;
    }
}
