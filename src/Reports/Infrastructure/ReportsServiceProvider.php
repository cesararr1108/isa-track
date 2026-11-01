<?php

declare(strict_types=1);

namespace Isa\Reports\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Isa\Reports\Application\Port\ReportData;
use Isa\Reports\Infrastructure\Persistence\EloquentReportData;

final class ReportsServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ReportData::class, fn () => new EloquentReportData((string) config('app.timezone')));
    }
}
