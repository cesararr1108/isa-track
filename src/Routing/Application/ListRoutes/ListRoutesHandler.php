<?php

declare(strict_types=1);

namespace Isa\Routing\Application\ListRoutes;

use Isa\Routing\Application\GetRoute\GetRouteHandler;
use Isa\Routing\Domain\DeliveryRoute;
use Isa\Routing\Domain\RouteRepository;
use Isa\Shared\Application\TenantContext;

final class ListRoutesHandler
{
    public function __construct(
        private readonly RouteRepository $routes,
        private readonly GetRouteHandler $getRoute,
        private readonly TenantContext $tenant,
    ) {}

    /**
     * @param  array{date?: string|null, from?: string|null, to?: string|null, carrier_id?: string|null, status?: string|null}  $filters
     * @return list<array<string, mixed>>
     */
    public function __invoke(array $filters): array
    {
        return array_map(
            fn (DeliveryRoute $r) => $this->getRoute->present($r),
            $this->routes->search($this->tenant->companyId(), $filters),
        );
    }
}
