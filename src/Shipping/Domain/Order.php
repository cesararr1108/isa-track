<?php

declare(strict_types=1);

namespace Isa\Shipping\Domain;

use DateTimeImmutable;
use Isa\Shared\Domain\AggregateRoot;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shared\Domain\ValueObject\Money;
use Isa\Shared\Domain\ValueObject\UserId;
use Isa\Shipping\Domain\Event\OrderCreated;
use Isa\Shipping\Domain\Event\OrderStatusChanged;
use Isa\Shipping\Domain\Exception\CarrierRequired;
use Isa\Shipping\Domain\Exception\CollectionAmountMismatch;
use Isa\Shipping\Domain\Exception\DeliveryRequiresProof;
use Isa\Shipping\Domain\Exception\IncidentReasonRequired;
use Isa\Shipping\Domain\Exception\InvalidStatusTransition;
use Isa\Shipping\Domain\Exception\OrderRequiresPackages;
use Ramsey\Uuid\Uuid as RamseyUuid;

/**
 * Agregado Pedido. Es la única puerta para modificar un pedido, sus paquetes
 * y su historial, así las reglas de negocio viven en un solo lugar.
 * No conoce Laravel, ni la base de datos, ni HTTP.
 */
final class Order extends AggregateRoot
{
    /** Estados que exigen un transportador asignado. */
    private const STATUSES_REQUIRING_CARRIER = [OrderStatus::Assigned, OrderStatus::InRoute, OrderStatus::Delivered];

    /**
     * @param  list<Package>  $packages
     * @param  list<StatusChange>  $history
     */
    private function __construct(
        private readonly OrderId $id,
        private readonly CompanyId $companyId,
        private readonly TrackingCode $trackingCode,
        private readonly ?string $externalReference,
        private Recipient $recipient,
        private Address $address,
        private array $packages,
        private OrderStatus $status,
        private ?CarrierId $carrierId,
        private readonly ?DateTimeImmutable $promisedAt,
        private readonly DateTimeImmutable $createdAt,
        private array $history,
        private readonly ?Money $cashOnDelivery,
        private ?Delivery $delivery,
    ) {}

    /**
     * @param  list<array{description: string, weight_kg?: float|null}>  $packages
     */
    public static function create(
        OrderId $id,
        CompanyId $companyId,
        TrackingCode $trackingCode,
        ?string $externalReference,
        Recipient $recipient,
        Address $address,
        array $packages,
        ?DateTimeImmutable $promisedAt,
        DateTimeImmutable $now,
        ?UserId $createdBy,
        ?Money $cashOnDelivery = null,
    ): self {
        if ($packages === []) {
            throw new OrderRequiresPackages;
        }

        $packageEntities = [];
        foreach (array_values($packages) as $i => $package) {
            $packageEntities[] = new Package(
                PackageId::generate(),
                sprintf('%s-%d', $trackingCode->value(), $i + 1),
                $package['description'],
                $package['weight_kg'] ?? null,
            );
        }

        $order = new self(
            $id, $companyId, $trackingCode, $externalReference, $recipient, $address,
            $packageEntities, OrderStatus::Created, null, $promisedAt, $now, [], $cashOnDelivery, null,
        );

        $order->appendHistory(OrderStatus::Created, $now, $createdBy);
        $order->record(new OrderCreated($id->value(), $companyId->value(), $trackingCode->value(), $now));
        $order->recordStatusChanged(null, $now);

        return $order;
    }

    /**
     * Reconstruye un pedido existente desde la base de datos (no valida ni dispara eventos).
     *
     * @param  list<Package>  $packages
     * @param  list<StatusChange>  $history
     */
    public static function restore(
        OrderId $id,
        CompanyId $companyId,
        TrackingCode $trackingCode,
        ?string $externalReference,
        Recipient $recipient,
        Address $address,
        array $packages,
        OrderStatus $status,
        ?CarrierId $carrierId,
        ?DateTimeImmutable $promisedAt,
        DateTimeImmutable $createdAt,
        array $history,
        ?Money $cashOnDelivery = null,
        ?Delivery $delivery = null,
    ): self {
        return new self(
            $id, $companyId, $trackingCode, $externalReference, $recipient, $address,
            $packages, $status, $carrierId, $promisedAt, $createdAt, $history, $cashOnDelivery, $delivery,
        );
    }

    /** Asigna (o reasigna) el pedido a un transportador. */
    public function assignCarrier(
        CarrierId $carrierId,
        StatusTransitionPolicy $policy,
        DateTimeImmutable $at,
        ?UserId $assignedBy,
    ): void {
        if ($this->status === OrderStatus::Assigned) {
            $this->carrierId = $carrierId;
            $this->appendHistory(OrderStatus::Assigned, $at, $assignedBy, note: 'Reasignado');

            return;
        }

        $this->guardTransition($this->status, OrderStatus::Assigned, $policy);
        $this->carrierId = $carrierId;
        $this->transitionTo(OrderStatus::Assigned, $at, $assignedBy);
    }

    public function changeStatus(
        OrderStatus $to,
        StatusTransitionPolicy $policy,
        DateTimeImmutable $at,
        ?UserId $changedBy,
        ?GeoPoint $location = null,
        ?string $note = null,
        ?IncidentReason $incidentReason = null,
    ): void {
        if ($to === OrderStatus::Delivered) {
            throw new DeliveryRequiresProof;
        }
        $this->guardTransition($this->status, $to, $policy);

        if (in_array($to, self::STATUSES_REQUIRING_CARRIER, true) && $this->carrierId === null) {
            throw new CarrierRequired;
        }
        if ($to === OrderStatus::Incident && $incidentReason === null) {
            throw new IncidentReasonRequired;
        }

        $this->transitionTo($to, $at, $changedBy, $location, $note, $incidentReason);
    }

    /**
     * Registra la entrega exitosa con su prueba y, si el pedido es contra
     * entrega, los pagos recibidos (deben sumar exactamente el valor a cobrar).
     *
     * @param  list<Payment>  $payments
     */
    public function deliver(
        DeliveryProof $proof,
        array $payments,
        DeliveryProofRequirements $requirements,
        StatusTransitionPolicy $policy,
        DateTimeImmutable $at,
        ?UserId $deliveredBy,
        ?string $note = null,
    ): void {
        $this->guardTransition($this->status, OrderStatus::Delivered, $policy);
        if ($this->carrierId === null) {
            throw new CarrierRequired;
        }
        $requirements->assertSatisfiedBy($proof);

        $delivery = new Delivery($proof, array_values($payments), $at);
        $expected = $this->cashOnDelivery ?? Money::zero();
        if (! $delivery->collectedTotal()->equals($expected)) {
            throw new CollectionAmountMismatch($expected->amount, $delivery->collectedTotal()->amount);
        }

        $this->delivery = $delivery;
        $this->transitionTo(OrderStatus::Delivered, $at, $deliveredBy, $proof->location, $note);
    }

    public function isCashOnDelivery(): bool
    {
        return $this->cashOnDelivery !== null && ! $this->cashOnDelivery->isZero();
    }

    public function isAssignedTo(CarrierId $carrierId): bool
    {
        return $this->carrierId !== null && $this->carrierId->equals($carrierId);
    }

    /** Última ubicación reportada en el historial (p. ej. por el conductor). */
    public function lastKnownLocation(): ?GeoPoint
    {
        for ($i = count($this->history) - 1; $i >= 0; $i--) {
            if ($this->history[$i]->location !== null) {
                return $this->history[$i]->location;
            }
        }

        return null;
    }

    private function guardTransition(OrderStatus $from, OrderStatus $to, StatusTransitionPolicy $policy): void
    {
        if (! $policy->allows($from, $to)) {
            throw new InvalidStatusTransition($from, $to);
        }
    }

    private function transitionTo(
        OrderStatus $to,
        DateTimeImmutable $at,
        ?UserId $by,
        ?GeoPoint $location = null,
        ?string $note = null,
        ?IncidentReason $incidentReason = null,
    ): void {
        $previous = $this->status;
        $this->status = $to;
        $this->appendHistory($to, $at, $by, $location, $note, $incidentReason);
        $this->recordStatusChanged($previous, $at);
    }

    private function appendHistory(
        OrderStatus $status,
        DateTimeImmutable $at,
        ?UserId $by,
        ?GeoPoint $location = null,
        ?string $note = null,
        ?IncidentReason $incidentReason = null,
    ): void {
        $this->history[] = new StatusChange(
            RamseyUuid::uuid7()->toString(), $status, $at, $by, $location, $note, $incidentReason,
        );
    }

    private function recordStatusChanged(?OrderStatus $previous, DateTimeImmutable $at): void
    {
        $this->record(new OrderStatusChanged(
            $this->id->value(),
            $this->companyId->value(),
            $this->trackingCode->value(),
            $previous?->value,
            $this->status->value,
            $this->recipient->name,
            $this->recipient->phone,
            $at,
        ));
    }

    public function id(): OrderId
    {
        return $this->id;
    }

    public function companyId(): CompanyId
    {
        return $this->companyId;
    }

    public function trackingCode(): TrackingCode
    {
        return $this->trackingCode;
    }

    public function externalReference(): ?string
    {
        return $this->externalReference;
    }

    public function recipient(): Recipient
    {
        return $this->recipient;
    }

    public function address(): Address
    {
        return $this->address;
    }

    /** @return list<Package> */
    public function packages(): array
    {
        return $this->packages;
    }

    public function status(): OrderStatus
    {
        return $this->status;
    }

    public function carrierId(): ?CarrierId
    {
        return $this->carrierId;
    }

    public function promisedAt(): ?DateTimeImmutable
    {
        return $this->promisedAt;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<StatusChange> */
    public function history(): array
    {
        return $this->history;
    }

    public function cashOnDelivery(): ?Money
    {
        return $this->cashOnDelivery;
    }

    public function delivery(): ?Delivery
    {
        return $this->delivery;
    }
}
