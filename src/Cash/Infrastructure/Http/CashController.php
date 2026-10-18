<?php

declare(strict_types=1);

namespace Isa\Cash\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Isa\Cash\Application\CashClosingPresenter;
use Isa\Cash\Application\CloseCashRegister\CloseCashRegisterCommand;
use Isa\Cash\Application\CloseCashRegister\CloseCashRegisterHandler;
use Isa\Cash\Application\GetCashSummary\GetCashSummaryHandler;
use Isa\Cash\Application\ListCashClosings\ListCashClosingsHandler;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Infrastructure\Auth\DriverScope;

final class CashController
{
    public function __construct(private readonly Clock $clock) {}

    /** GET /api/cash/summary?carrier_id=&date=  (el conductor ve solo su caja) */
    public function summary(Request $request, GetCashSummaryHandler $getSummary): JsonResponse
    {
        $data = $request->validate([
            'carrier_id' => ['nullable', 'uuid'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $carrierId = DriverScope::carrierId($request) ?? $data['carrier_id'] ?? null;
        abort_if($carrierId === null, 422, 'Indica carrier_id.');

        return new JsonResponse(['data' => $getSummary($carrierId, $data['date'] ?? $this->today())]);
    }

    /** GET /api/cash/closings?date= */
    public function index(Request $request, ListCashClosingsHandler $listClosings): JsonResponse
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return new JsonResponse(['data' => $listClosings($data['date'] ?? $this->today())]);
    }

    /** POST /api/cash/closings {carrier_id, date?, cash_received, notes?} */
    public function store(Request $request, CloseCashRegisterHandler $closeRegister): JsonResponse
    {
        $data = $request->validate([
            'carrier_id' => ['required', 'uuid'],
            'date' => ['nullable', 'date_format:Y-m-d'],
            'cash_received' => ['required', 'integer', 'min:0'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $closing = $closeRegister(new CloseCashRegisterCommand(
            $data['carrier_id'],
            $data['date'] ?? $this->today(),
            (int) $data['cash_received'],
            $data['notes'] ?? null,
            (string) $request->user()->getAuthIdentifier(),
        ));

        return new JsonResponse(['data' => CashClosingPresenter::closing($closing)], 201);
    }

    private function today(): string
    {
        return $this->clock->now()->format('Y-m-d');
    }
}
