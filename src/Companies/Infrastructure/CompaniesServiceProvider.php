<?php

declare(strict_types=1);

namespace Isa\Companies\Infrastructure;

use Illuminate\Support\ServiceProvider;
use Isa\Companies\Application\CreateCompany\CreateCompanyHandler;
use Isa\Companies\Domain\CompanyRepository;
use Isa\Companies\Infrastructure\Persistence\EloquentCompanyRepository;

final class CompaniesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CompanyRepository::class, EloquentCompanyRepository::class);

        $this->app->bind(CreateCompanyHandler::class, fn ($app) => new CreateCompanyHandler(
            $app->make(CompanyRepository::class),
            config('isa.branding'),
        ));
    }
}
