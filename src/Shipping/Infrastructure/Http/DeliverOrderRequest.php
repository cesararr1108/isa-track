<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Http;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Isa\Shared\Infrastructure\Auth\DriverScope;
use Isa\Shipping\Application\DeliverOrder\DeliverOrderCommand;
use Isa\Shipping\Application\Port\EvidenceFile;
use Isa\Shipping\Domain\PaymentMethod;

/**
 * Formato de un registro de entrega (multipart/form-data). Lo usan la API
 * (/api/orders/{id}/deliver) y la app del mensajero (/mensajero/pedidos/{id}/entregar).
 * Qué es obligatorio (foto, firma, nombre, GPS) y si los cobros cuadran lo decide el dominio.
 */
final class DeliverOrderRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        $maxKb = (int) config('isa.delivery_proof.max_file_kb');

        return [
            'receiver_name' => ['nullable', 'string', 'max:150'],
            'receiver_document' => ['nullable', 'string', 'max:30'],
            'photo' => ['nullable', 'image', "max:$maxKb"],
            'signature' => ['nullable', 'image', "max:$maxKb"],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
            'note' => ['nullable', 'string', 'max:500'],
            'payments' => ['nullable', 'array', 'max:5'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'integer', 'min:1'],
            'payments.*.reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    /** @return array<string, string> */
    public function attributes(): array
    {
        return [
            'receiver_name' => 'nombre de quien recibe',
            'receiver_document' => 'documento de quien recibe',
            'photo' => 'foto de la entrega',
            'signature' => 'firma',
            'payments.*.method' => 'medio de pago',
            'payments.*.amount' => 'valor cobrado',
            'payments.*.reference' => 'referencia del pago',
        ];
    }

    public function toCommand(string $orderId): DeliverOrderCommand
    {
        $data = $this->validated();

        return new DeliverOrderCommand(
            orderId: $orderId,
            receiverName: $data['receiver_name'] ?? null,
            receiverDocument: $data['receiver_document'] ?? null,
            photo: self::evidence($this->file('photo')),
            signature: self::evidence($this->file('signature')),
            latitude: isset($data['latitude']) ? (float) $data['latitude'] : null,
            longitude: isset($data['longitude']) ? (float) $data['longitude'] : null,
            payments: array_map(fn (array $p) => [
                'method' => $p['method'],
                'amount' => (int) $p['amount'],
                'reference' => $p['reference'] ?? null,
            ], array_values($data['payments'] ?? [])),
            note: $data['note'] ?? null,
            deliveredBy: (string) $this->user()->getAuthIdentifier(),
            actorCarrierId: DriverScope::carrierId($this),
        );
    }

    private static function evidence(mixed $file): ?EvidenceFile
    {
        return $file instanceof UploadedFile ? new EvidenceFile($file->getRealPath(), $file->guessExtension() ?? 'bin') : null;
    }
}
