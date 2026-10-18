<?php

declare(strict_types=1);

namespace Isa\Cash\Application\ListCashClosings;

use Isa\Cash\Application\CashClosingPresenter;
use Isa\Cash\Domain\BusinessDate;
use Isa\Cash\Domain\CashClosing;
use Isa\Cash\Domain\CashClosingRepository;
use Isa\Shared\Application\TenantContext;

final class ListCashClosingsHandler
{
    public function __construct(
        private readonly CashClosingRepository $closings,
        private readonly TenantContext $tenant,
    ) {}

    /** @return list<array<string, mixed>> */
    public function __invoke(string $date): array
    {
        return array_map(
            fn (CashClosing $c) => CashClosingPresenter::closing($c),
            $this->closings->listFor($this->tenant->companyId(), new BusinessDate($date)),
        );
    }
}
