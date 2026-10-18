<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\ListOrders;

use Isa\Shared\Application\TenantContext;
use Isa\Shipping\Application\Port\OrderQueries;

final class ListOrdersHandler
{
    private const MAX_PER_PAGE = 100;

    public function __construct(
        private readonly OrderQueries $queries,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{status?: ?string, carrier_id?: ?string, search?: ?string}  $filters
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int}}
     */
    public function __invoke(array $filters, int $page = 1, int $perPage = 20): array
    {
        return $this->queries->search(
            $this->tenant->companyId(),
            $filters,
            max(1, $page),
            min(self::MAX_PER_PAGE, max(1, $perPage)),
        );
    }
}
