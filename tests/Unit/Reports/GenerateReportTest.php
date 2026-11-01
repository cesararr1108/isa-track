<?php

declare(strict_types=1);

namespace Tests\Unit\Reports;

use InvalidArgumentException;
use Isa\Reports\Application\GenerateReport\GenerateReportHandler;
use Isa\Reports\Application\Port\ReportData;
use Isa\Reports\Application\Port\ReportIncidentRow;
use Isa\Reports\Application\Port\ReportOrderRow;
use Isa\Reports\Application\Port\ReportPaymentRow;
use Isa\Reports\Domain\DateRange;
use Isa\Shared\Application\TenantContext;
use Isa\Shared\Domain\ValueObject\CompanyId;
use PHPUnit\Framework\TestCase;

final class GenerateReportTest extends TestCase
{
    public function test_date_range_rules(): void
    {
        self::assertSame(3, (new DateRange('2026-10-30', '2026-11-01'))->days());
        self::assertSame(['2026-10-31', '2026-11-01'], (new DateRange('2026-10-31', '2026-11-01'))->eachDay());

        foreach ([['2026-11-02', '2026-11-01'], ['2025-01-01', '2026-11-01'], ['2026-02-30', '2026-03-01']] as [$from, $to]) {
            try {
                new DateRange($from, $to);
                self::fail("$from..$to debía fallar");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }
    }

    public function test_aggregates_by_status_day_carrier_zone_and_method(): void
    {
        $o = fn (string $id, string $day, string $status, ?string $carrier, ?string $zone, int $fee, ?string $delivered = null, ?bool $onTime = null) => new ReportOrderRow($id, 'ISA-'.$id, $day, $status, $carrier, $zone, 'Barrio', 'Cliente', $fee, 0, $delivered, $onTime);

        $data = new class([$o('1', '2026-10-31', 'delivered', 'c1', 'z1', 8000, '2026-10-31', true), $o('2', '2026-10-31', 'delivered', 'c1', 'z1', 8000, '2026-11-01', false), $o('3', '2026-11-01', 'returned', 'c2', 'z2', 6000), $o('4', '2026-11-01', 'created', null, null, 0)]) implements ReportData
        {
            public function __construct(private array $orders) {}

            public function orders(CompanyId $companyId, DateRange $range, array $filters): array
            {
                return $this->orders;
            }

            public function payments(CompanyId $companyId, DateRange $range, array $filters): array
            {
                return [new ReportPaymentRow('c1', 'cash', 50000, '2026-10-31'), new ReportPaymentRow('c1', 'nequi', 20000, '2026-11-01')];
            }

            public function incidents(CompanyId $companyId, DateRange $range, array $filters): array
            {
                return [new ReportIncidentRow('customer_absent', 'c2', '2026-11-01'), new ReportIncidentRow('customer_absent', 'c2', '2026-11-01')];
            }

            public function carrierNames(CompanyId $companyId): array
            {
                return ['c1' => 'Juan', 'c2' => 'Camila'];
            }

            public function zoneNames(CompanyId $companyId): array
            {
                return ['z1' => 'El Poblado', 'z2' => 'Centro'];
            }
        };
        $tenant = new class implements TenantContext
        {
            public function companyId(): CompanyId
            {
                return CompanyId::fromString('01a19761-2c5b-71eb-809b-26f0fd44b2ae');
            }

            public function hasCompany(): bool
            {
                return true;
            }
        };

        $r = (new GenerateReportHandler($data, $tenant))('2026-10-31', '2026-11-01');

        self::assertSame(4, $r['totals']['orders']);
        self::assertSame(2, $r['totals']['delivered']);
        self::assertEqualsWithDelta(66.7, $r['totals']['delivery_rate'], 0.01);
        self::assertSame(50.0, $r['totals']['on_time_rate']);
        self::assertSame(22000, $r['totals']['delivery_fees']);
        self::assertSame(70000, $r['totals']['collected']);
        self::assertSame(50000, $r['totals']['collected_cash']);
        self::assertSame(2, $r['totals']['incidents_reported']);

        self::assertSame([2, 2], [$r['by_day'][0]['created'], $r['by_day'][1]['created']]);
        self::assertSame([1, 1], [$r['by_day'][0]['delivered'], $r['by_day'][1]['delivered']]);

        self::assertSame('Juan', $r['by_carrier'][0]['name']);
        self::assertSame(70000, $r['by_carrier'][0]['collected']);
        $camila = array_values(array_filter($r['by_carrier'], fn ($c) => $c['name'] === 'Camila'))[0];
        self::assertSame(2, $camila['incidents']);
        self::assertSame(0.0, $camila['delivery_rate']);

        self::assertSame('El Poblado', $r['by_zone'][0]['name']);
        self::assertContains('Fuera de zona', array_column($r['by_zone'], 'name'));
        self::assertSame(['cash' => 50000, 'nequi' => 20000], $r['collections_by_method']);
        self::assertSame(['customer_absent' => 2], $r['incidents_by_reason']);
    }
}
