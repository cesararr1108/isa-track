<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;
use Isa\Shipping\Application\CreateOrder\CreateOrderCommand;

/**
 * FormRequest = validación de formato de la petición HTTP (Laravel la ejecuta
 * antes de entrar al controlador y responde 422 si falla). Las reglas de
 * negocio NO van aquí, van en el dominio.
 */
final class CreateOrderRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'external_reference' => ['nullable', 'string', 'max:60'],
            'promised_at' => ['nullable', 'date'],
            'cash_on_delivery' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'recipient.name' => ['required', 'string', 'max:150'],
            'recipient.phone' => ['required', 'string', 'max:20'],
            'recipient.email' => ['nullable', 'email'],
            'address.line' => ['required', 'string', 'max:200'],
            'address.city' => ['nullable', 'string', 'max:80'],
            'address.neighborhood' => ['nullable', 'string', 'max:80'],
            'address.reference' => ['nullable', 'string', 'max:200'],
            'address.latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:address.longitude'],
            'address.longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:address.latitude'],
            'packages' => ['required', 'array', 'min:1', 'max:50'],
            'packages.*.description' => ['required', 'string', 'max:150'],
            'packages.*.weight_kg' => ['nullable', 'numeric', 'min:0'],
        ];
    }

    public function toCommand(string $defaultCity): CreateOrderCommand
    {
        $lat = $this->input('address.latitude');
        $lng = $this->input('address.longitude');

        return new CreateOrderCommand(
            recipientName: $this->input('recipient.name'),
            recipientPhone: $this->input('recipient.phone'),
            recipientEmail: $this->input('recipient.email'),
            addressLine: $this->input('address.line'),
            city: $this->input('address.city') ?: $defaultCity,
            neighborhood: $this->input('address.neighborhood'),
            addressReference: $this->input('address.reference'),
            latitude: $lat !== null ? (float) $lat : null,
            longitude: $lng !== null ? (float) $lng : null,
            packages: array_map(fn (array $p) => [
                'description' => $p['description'],
                'weight_kg' => isset($p['weight_kg']) ? (float) $p['weight_kg'] : null,
            ], $this->input('packages')),
            externalReference: $this->input('external_reference'),
            promisedAt: $this->input('promised_at'),
            createdBy: (string) $this->user()->getAuthIdentifier(),
            cashOnDelivery: $this->filled('cash_on_delivery') ? (int) $this->input('cash_on_delivery') : null,
        );
    }
}
