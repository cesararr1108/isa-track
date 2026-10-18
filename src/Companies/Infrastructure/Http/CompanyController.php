<?php

declare(strict_types=1);

namespace Isa\Companies\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Isa\Companies\Application\CreateCompany\CreateCompanyCommand;
use Isa\Companies\Application\CreateCompany\CreateCompanyHandler;

final class CompanyController
{
    public function store(Request $request, CreateCompanyHandler $createCompany): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'tax_id' => ['nullable', 'string', 'max:30'],
            'logo_url' => ['nullable', 'url'],
            'primary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'secondary_color' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
        ]);

        $id = $createCompany(new CreateCompanyCommand(
            $data['name'],
            $data['tax_id'] ?? null,
            $data['logo_url'] ?? null,
            $data['primary_color'] ?? null,
            $data['secondary_color'] ?? null,
        ));

        return new JsonResponse(['id' => $id->value()], 201);
    }
}
