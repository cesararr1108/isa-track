<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

/** Regla que decide si un pedido puede pasar de un estado a otro. */
interface StatusTransitionPolicy
{
    public function allows(OrderStatus $from, OrderStatus $to): bool;
}
