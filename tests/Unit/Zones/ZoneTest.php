<?php

declare(strict_types=1);

namespace Tests\Unit\Zones;

use InvalidArgumentException;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shared\Domain\ValueObject\GeoPoint;
use Isa\Shared\Domain\ValueObject\Money;
use Isa\Zones\Domain\Polygon;
use Isa\Zones\Domain\Zone;
use Isa\Zones\Domain\ZoneId;
use Isa\Zones\Domain\ZoneResolver;
use PHPUnit\Framework\TestCase;

final class ZoneTest extends TestCase
{
    private const POBLADO = [[6.2250, -75.5800], [6.2240, -75.5530], [6.2000, -75.5450], [6.1850, -75.5560], [6.1870, -75.5790]];

    public function test_polygon_contains_points_inside_and_on_border(): void
    {
        $p = Polygon::fromPairs(self::POBLADO);

        self::assertTrue($p->contains(new GeoPoint(6.2088, -75.5679)), 'Parque Lleras');
        self::assertFalse($p->contains(new GeoPoint(6.2442, -75.5966)), 'Laureles');
        self::assertTrue($p->contains(new GeoPoint(6.2250, -75.5800)), 'un vértice');
    }

    public function test_polygon_needs_three_points_and_drops_closing_vertex(): void
    {
        $closed = Polygon::fromPairs([[0, 0], [0, 1], [1, 1], [0, 0]]);
        self::assertCount(3, $closed->vertices);

        $this->expectException(InvalidArgumentException::class);
        Polygon::fromPairs([[0, 0], [0, 1]]);
    }

    public function test_polygon_rejects_zero_area(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Polygon::fromPairs([[0, 0], [0, 1], [0, 2]]);
    }

    public function test_resolver_picks_smallest_zone_and_ignores_inactive(): void
    {
        $company = CompanyId::generate();
        $big = Zone::create(ZoneId::generate(), $company, 'Medellín', '#0B1F3A', Polygon::fromPairs([[6.30, -75.65], [6.30, -75.50], [6.15, -75.50], [6.15, -75.65]]), new Money(12000));
        $small = Zone::create(ZoneId::generate(), $company, 'El Poblado', '#00C896', Polygon::fromPairs(self::POBLADO), new Money(8000));
        $resolver = new ZoneResolver;
        $lleras = new GeoPoint(6.2088, -75.5679);

        self::assertSame('El Poblado', $resolver->resolve([$big, $small], $lleras)?->name());

        $small->update('El Poblado', '#00C896', $small->area(), new Money(8000), null, false);
        self::assertSame('Medellín', $resolver->resolve([$big, $small], $lleras)?->name());
        self::assertNull($resolver->resolve([$small], $lleras));
    }

    public function test_zone_validates_color(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Zone::create(ZoneId::generate(), CompanyId::generate(), 'X', 'verde', Polygon::fromPairs(self::POBLADO), new Money(1));
    }
}
