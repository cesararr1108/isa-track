<?php

declare(strict_types=1);

namespace App\Http\Controllers\Web;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Isa\Reports\Application\GenerateReport\GenerateReportHandler;
use Isa\Shared\Domain\Clock;
use Isa\Shared\Infrastructure\Auth\PermissionChecker;

/** Resumen del día. Las cifras solo se calculan si el usuario puede ver reportes. */
final class DashboardController
{
    public function __invoke(Request $request, GenerateReportHandler $report, Clock $clock, PermissionChecker $permissions): Response
    {
        $user = $request->user();
        $today = $clock->now()->format('Y-m-d');
        $canSee = $permissions->allows($user->role, 'reports.view', $user->permissions ?? [], $user->revoked_permissions ?? []);

        return Inertia::render('dashboard/pages/Dashboard', [
            'today' => $today,
            'summary' => $canSee ? $report($today, $today) : null,
            'statuses' => config('isa.statuses'),
            'incidentReasons' => config('isa.incident_reasons'),
            'paymentMethods' => config('isa.payment_methods'),
        ]);
    }
}
