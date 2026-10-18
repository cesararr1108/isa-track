<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\DeliverOrder;

use Isa\Shared\Application\EventBus;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Application\TransactionManager;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shared\Domain\ValueObject\Money;
use Isa\Shared\Domain\ValueObject\UserId;
use Isa\Shipping\Application\Port\CashRegisterStatus;
use Isa\Shipping\Application\Port\EvidenceStorage;
use Isa\Shipping\Domain\DeliveryProof;
use Isa\Shipping\Domain\DeliveryProofRequirements;
use Isa\Shipping\Domain\Exception\CashRegisterAlreadyClosed;
use Isa\Shipping\Domain\Exception\OrderNotAssignedToActor;
use Isa\Shipping\Domain\Exception\OrderNotFound;
use Isa\Shipping\Domain\Order;
use Isa\Shipping\Domain\OrderId;
use Isa\Shipping\Domain\OrderRepository;
use Isa\Shipping\Domain\Payment;
use Isa\Shipping\Domain\PaymentMethod;
use Isa\Shipping\Domain\StatusTransitionPolicy;
use Ramsey\Uuid\Uuid;
use Throwable;

/**
 * El conductor registra la entrega: foto, firma, quién recibe, GPS y,
 * si es contra entrega, cuánto cobró y por qué medio.
 */
final class DeliverOrderHandler
{
    public function __construct(
        private readonly OrderRepository $orders,
        private readonly EvidenceStorage $evidence,
        private readonly CashRegisterStatus $cashRegister,
        private readonly StatusTransitionPolicy $transitions,
        private readonly DeliveryProofRequirements $requirements,
        private readonly TenantContext $tenant,
        private readonly Clock $clock,
        private readonly TransactionManager $transaction,
        private readonly EventBus $events,
    ) {}

    public function __invoke(DeliverOrderCommand $command): Order
    {
        $companyId = $this->tenant->companyId();
        $orderId = OrderId::fromString($command->orderId);
        $now = $this->clock->now();

        $order = $this->orders->find($companyId, $orderId) ?? throw new OrderNotFound($command->orderId);

        if ($command->actorCarrierId !== null && ! $order->isAssignedTo(CarrierId::fromString($command->actorCarrierId))) {
            throw new OrderNotAssignedToActor;
        }

        $date = $now->format('Y-m-d');
        if ($order->carrierId() !== null && $command->payments !== []
            && $this->cashRegister->isClosed($companyId, $order->carrierId(), $date)) {
            throw new CashRegisterAlreadyClosed($date);
        }

        $payments = array_map(fn (array $p) => new Payment(
            Uuid::uuid7()->toString(),
            PaymentMethod::from($p['method']),
            new Money((int) $p['amount']),
            $p['reference'] ?? null,
        ), $command->payments);

        // Los archivos se guardan primero; si la entrega es rechazada se borran.
        $stored = [];
        try {
            $photoPath = $command->photo ? $stored[] = $this->evidence->store($companyId, $orderId, 'photo', $command->photo) : null;
            $signaturePath = $command->signature ? $stored[] = $this->evidence->store($companyId, $orderId, 'signature', $command->signature) : null;

            $order = $this->transaction->run(function () use ($command, $companyId, $orderId, $payments, $photoPath, $signaturePath, $now) {
                // Se recarga dentro de la transacción para trabajar sobre la versión vigente.
                $order = $this->orders->find($companyId, $orderId) ?? throw new OrderNotFound($command->orderId);

                $order->deliver(
                    new DeliveryProof(
                        $command->receiverName,
                        $command->receiverDocument,
                        $photoPath,
                        $signaturePath,
                        GeoPoint::fromNullable($command->latitude, $command->longitude),
                    ),
                    $payments,
                    $this->requirements,
                    $this->transitions,
                    $now,
                    $command->deliveredBy !== null ? UserId::fromString($command->deliveredBy) : null,
                    $command->note,
                );
                $this->orders->save($order);

                return $order;
            });
        } catch (Throwable $e) {
            foreach ($stored as $path) {
                $this->evidence->delete($path);
            }
            throw $e;
        }

        $this->events->publish(...$order->pullDomainEvents());

        return $order;
    }
}
