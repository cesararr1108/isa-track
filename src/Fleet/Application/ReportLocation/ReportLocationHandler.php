<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\ReportLocation;

use Isa\Fleet\Application\GetCarrier\GetCarrierHandler;
use Isa\Fleet\Domain\CarrierRepository;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Domain\ValueObject\GeoPoint;

/** Guarda la última ubicación GPS que reporta el mensajero (mapa en vivo). */
final class ReportLocationHandler
{
    public function __construct(
        private readonly CarrierRepository $carriers,
        private readonly GetCarrierHandler $getCarrier,
        private readonly Clock $clock,
    ) {}

    public function __invoke(string $carrierId, float $latitude, float $longitude): void
    {
        $carrier = ($this->getCarrier)($carrierId);
        $carrier->reportLocation(new GeoPoint($latitude, $longitude), $this->clock->now());
        $this->carriers->save($carrier);
    }
}
