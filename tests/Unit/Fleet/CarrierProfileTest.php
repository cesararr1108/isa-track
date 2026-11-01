<?php

declare(strict_types=1);

namespace Tests\Unit\Fleet;

use InvalidArgumentException;
use Isa\Fleet\Domain\Carrier;
use Isa\Fleet\Domain\CarrierProfile;
use Isa\Fleet\Domain\CarrierType;
use Isa\Fleet\Domain\VehicleCapacity;
use Isa\Fleet\Domain\VehicleType;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use PHPUnit\Framework\TestCase;

final class CarrierProfileTest extends TestCase
{
    public function test_normalizes_plate_and_document(): void
    {
        $p = new CarrierProfile('1.037.654.321', '+57 300 111 2233', 'abc-12d', VehicleType::Motorcycle);

        self::assertSame('1037654321', $p->documentNumber);
        self::assertSame('ABC12D', $p->vehiclePlate);
    }

    public function test_rejects_invalid_plate_document_and_capacity(): void
    {
        foreach ([
            fn () => new CarrierProfile(vehiclePlate: 'AB'),
            fn () => new CarrierProfile(documentNumber: '12'),
            fn () => new CarrierProfile(phone: 'llámame'),
            fn () => new VehicleCapacity(0),
        ] as $i => $make) {
            try {
                $make();
                self::fail("Caso $i debía fallar");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_capacity_and_photo(): void
    {
        $carrier = Carrier::register(CarrierId::generate(), CompanyId::generate(), 'Juan', CarrierType::OwnDriver,
            new CarrierProfile(capacity: new VehicleCapacity(40, 10)));

        self::assertTrue($carrier->canCarry(40, 10));
        self::assertFalse($carrier->canCarry(41, 1));
        self::assertFalse($carrier->canCarry(1, 11));

        self::assertNull($carrier->changePhoto('a.jpg'));
        self::assertSame('a.jpg', $carrier->changePhoto('b.jpg'));
        self::assertSame('b.jpg', $carrier->photoPath());
    }
}
