<?php

declare(strict_types=1);

namespace Isa\Zones\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;
use Isa\Zones\Application\SaveZone\SaveZoneCommand;

/** Validación de formato de una zona. La usan la API y el panel web. */
final class ZoneRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:80'],
            'color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'delivery_fee' => ['required', 'integer', 'min:0', 'max:10000000'],
            'estimated_minutes' => ['nullable', 'integer', 'min:1', 'max:1440'],
            'active' => ['nullable', 'boolean'],
            'polygon' => ['required', 'array', 'min:3', 'max:500'],
            'polygon.*' => ['required', 'array', 'size:2'],
            'polygon.*.0' => ['required', 'numeric', 'between:-90,90'],
            'polygon.*.1' => ['required', 'numeric', 'between:-180,180'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return ['name' => 'nombre', 'delivery_fee' => 'tarifa', 'estimated_minutes' => 'tiempo estimado', 'polygon' => 'área de la zona'];
    }

    public function toCommand(?string $zoneId = null): SaveZoneCommand
    {
        $d = $this->validated();

        return new SaveZoneCommand(
            name: $d['name'],
            color: $d['color'] ?? '#00C896',
            polygon: array_map(fn (array $p) => [(float) $p[0], (float) $p[1]], $d['polygon']),
            deliveryFee: (int) $d['delivery_fee'],
            estimatedMinutes: isset($d['estimated_minutes']) ? (int) $d['estimated_minutes'] : null,
            active: $this->has('active') ? $this->boolean('active') : true,
            zoneId: $zoneId,
        );
    }
}
