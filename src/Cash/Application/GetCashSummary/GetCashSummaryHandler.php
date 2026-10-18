<?php

declare(strict_types=1);

namespace Isa\Cash\Application\GetCashSummary;

use Isa\Cash\Application\CashClosingPresenter;
use Isa\Cash\Application\Port\CollectedPayments;
use Isa\Cash\Domain\BusinessDate;
use Isa\Cash\Domain\CashClosingRepository;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Domain\ValueObject\CarrierId;

/** Resumen del día de un conductor: lo recaudado por medio de pago y si ya se cuadró. */
final class GetCashSummaryHandler
{
    public function __construct(
        private readonly CollectedPayments $payments,
        private readonly CashClosingRepository $closings,
        private readonly TenantContext $tenant,
    ) {}

    /** @return array<string, mixed> */
    public function __invoke(string $carrierId, string $date): array
    {
        $companyId = $this->tenant->companyId();
        $carrier = CarrierId::fromString($carrierId);
        $day = new BusinessDate($date);

        $closing = $this->closings->findFor($companyId, $carrier, $day);

        return [
            'carrier_id' => $carrier->value(),
            'date' => $day->value,
            // Si ya se cerró se muestra la foto del cierre, no un recálculo.
            'collected' => CashClosingPresenter::summary($closing?->expected() ?? $this->payments->summaryFor($companyId, $carrier, $day)),
            'closing' => $closing !== null ? CashClosingPresenter::closing($closing) : null,
        ];
    }
}
