<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Persistence;

use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shipping\Application\Port\OrderQueries;

final class EloquentOrderQueries implements OrderQueries
{
    public function search(CompanyId $companyId, array $filters, int $page, int $perPage): array
    {
        $query = OrderModel::query()
            ->where('company_id', $companyId->value())
            ->when($filters['status'] ?? null, fn ($q, $status) => $q->where('status', $status))
            ->when($filters['carrier_id'] ?? null, fn ($q, $carrier) => $q->where('carrier_id', $carrier))
            ->when($filters['search'] ?? null, fn ($q, $term) => $q->where(function ($q) use ($term): void {
                $like = '%'.mb_strtolower($term).'%';
                $q->whereRaw('LOWER(tracking_code) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(external_reference) LIKE ?', [$like])
                    ->orWhereRaw('LOWER(recipient_name) LIKE ?', [$like]);
            }))
            ->withCount('packages')
            ->orderByDesc('created_at');

        $paginator = $query->paginate($perPage, ['*'], 'page', $page);

        return [
            'data' => collect($paginator->items())->map(fn (OrderModel $o) => [
                'id' => $o->id,
                'tracking_code' => $o->tracking_code,
                'external_reference' => $o->external_reference,
                'status' => $o->status,
                'carrier_id' => $o->carrier_id,
                'recipient_name' => $o->recipient_name,
                'city' => $o->city,
                'neighborhood' => $o->neighborhood,
                'packages_count' => $o->packages_count,
                'promised_at' => $o->promised_at?->format(DATE_ATOM),
                'created_at' => $o->created_at?->format(DATE_ATOM),
            ])->all(),
            'meta' => ['page' => $paginator->currentPage(), 'per_page' => $perPage, 'total' => $paginator->total()],
        ];
    }
}
