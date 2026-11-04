<?php

declare(strict_types=1);

namespace Isa\Cash\Infrastructure\Http\Web;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Cash\Application\CloseCashRegister\CloseCashRegisterCommand;
use Isa\Cash\Application\CloseCashRegister\CloseCashRegisterHandler;
use Isa\Cash\Application\GetCashSummary\GetCashSummaryHandler;
use Isa\Fleet\Application\ListCarriers\ListCarriersHandler;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Infrastructure\Audit\AuditLog;
use Isa\Shared\Infrastructure\Auth\DriverScope;

/** Cuadre de caja del día por mensajero (panel). El mensajero solo ve la suya. */
final class CashPageController
{
    public function __construct(private readonly Clock $clock) {}

    public function index(Request $request, ListCarriersHandler $carriers, GetCashSummaryHandler $summary): Response
    {
        $d = $request->validate(['date' => ['nullable', 'date_format:Y-m-d', 'before_or_equal:today']]);
        $date = $d['date'] ?? $this->clock->now()->format('Y-m-d');
        $driver = DriverScope::carrierId($request);

        $rows = [];
        foreach ($carriers() as $c) {
            if ($driver !== null && $c['id'] !== $driver) {
                continue;
            }
            $rows[] = ['carrier' => ['id' => $c['id'], 'name' => $c['name'], 'active' => $c['active']]] + $summary($c['id'], $date);
        }
        // Primero los que tienen recaudo o cierre.
        usort($rows, fn ($a, $b) => [$b['collected']['total'] > 0 || $b['closing'] !== null, $a['carrier']['name']] <=> [$a['collected']['total'] > 0 || $a['closing'] !== null, $b['carrier']['name']]);

        return Inertia::render('cash/pages/CashIndex', [
            'date' => $date,
            'today' => $this->clock->now()->format('Y-m-d'),
            'rows' => $rows,
            'paymentMethods' => config('isa.payment_methods'),
            'requireNote' => (bool) config('isa.cash.require_note_on_difference'),
        ]);
    }

    public function close(Request $request, CloseCashRegisterHandler $close, AuditLog $audit): RedirectResponse
    {
        $d = $request->validate([
            'carrier_id' => ['required', 'uuid'],
            'date' => ['required', 'date_format:Y-m-d', 'before_or_equal:today'],
            'cash_received' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'notes' => ['nullable', 'string', 'max:500'],
        ], [], ['cash_received' => 'efectivo recibido', 'notes' => 'nota']);

        $closing = $close(new CloseCashRegisterCommand(
            $d['carrier_id'], $d['date'], (int) $d['cash_received'], $d['notes'] ?? null, (string) $request->user()->getAuthIdentifier(),
        ));
        $audit->record('cash.closed', 'carrier', $d['carrier_id'], [
            'date' => $d['date'], 'expected' => $closing->expected()->expectedCash()->amount,
            'received' => $closing->cashReceived()->amount, 'result' => $closing->result(),
        ]);

        return back()->with('success', match ($closing->result()) {
            'balanced' => 'Caja cerrada: cuadra exacto.',
            'short' => 'Caja cerrada con faltante.',
            default => 'Caja cerrada con sobrante.',
        });
    }
}
