<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

/**
 * Implementación de StatusTransitionPolicy basada en un mapa
 * ['estado_origen' => ['estado_destino', ...]].
 */
final class TransitionMap implements StatusTransitionPolicy
{
    /** @var array<string, list<OrderStatus>> */
    private array $map = [];

    /** @param array<string, list<string>> $map */
    public function __construct(array $map)
    {
        foreach ($map as $from => $targets) {
            $this->map[OrderStatus::from($from)->value] = array_map(
                static fn (string $to) => OrderStatus::from($to),
                $targets,
            );
        }
    }

    public function allows(OrderStatus $from, OrderStatus $to): bool
    {
        return in_array($to, $this->map[$from->value] ?? [], true);
    }
}
