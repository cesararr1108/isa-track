<?php

declare(strict_types=1);

namespace Isa\Cash\Application\CloseCashRegister;

use Isa\Cash\Application\Port\CarrierLookup;
use Isa\Cash\Application\Port\CollectedPayments;
use Isa\Cash\Domain\BusinessDate;
use Isa\Cash\Domain\CashClosing;
use Isa\Cash\Domain\CashClosingId;
use Isa\Cash\Domain\CashClosingRepository;
use Isa\Cash\Domain\Exception\CarrierNotFound;
use Isa\Cash\Domain\Exception\CashAlreadyClosed;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Application\TransactionManager;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\Money;
use Isa\Shared\Domain\ValueObject\UserId;

/**
 * Cierra la caja de un conductor para un día. Toma lo recaudado según el
 * sistema, lo compara con el efectivo entregado y deja el registro fijo.
 */
final class CloseCashRegisterHandler
{
    public function __construct(
        private readonly CollectedPayments $payments,
        private readonly CashClosingRepository $closings,
        private readonly CarrierLookup $carriers,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
        private readonly TransactionManager $transaction,
        private readonly bool $requireNoteOnDifference,
    ) {}

    public function __invoke(CloseCashRegisterCommand $command): CashClosing
    {
        $companyId = $this->tenant->companyId();
        $carrierId = CarrierId::fromString($command->carrierId);
        $date = new BusinessDate($command->date);

        if (! $this->carriers->exists($companyId, $carrierId)) {
            throw new CarrierNotFound($command->carrierId);
        }

        return $this->transaction->run(function () use ($command, $companyId, $carrierId, $date) {
            if ($this->closings->findFor($companyId, $carrierId, $date) !== null) {
                throw new CashAlreadyClosed($date->value);
            }

            $closing = CashClosing::close(
                CashClosingId::generate(),
                $companyId,
                $carrierId,
                $date,
                $this->payments->summaryFor($companyId, $carrierId, $date),
                new Money($command->cashReceived),
                $command->notes,
                $this->requireNoteOnDifference,
                $command->closedBy !== null ? UserId::fromString($command->closedBy) : null,
                $this->clock->now(),
            );
            $this->closings->save($closing);

            return $closing;
        });
    }
}
