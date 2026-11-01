<?php

declare(strict_types=1);

namespace Isa\Fleet\Infrastructure\Adapter;

use Illuminate\Contracts\Filesystem\Filesystem;
use Isa\Fleet\Application\Port\CarrierPhotoStorage;
use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Ramsey\Uuid\Uuid;

/** Ruta: carriers/{empresa}/{transportador}/photo-{uuid}.{ext} */
final class LaravelCarrierPhotoStorage implements CarrierPhotoStorage
{
    public function __construct(private readonly Filesystem $disk) {}

    public function store(CompanyId $companyId, CarrierId $carrierId, string $localPath, string $extension): string
    {
        $path = sprintf('carriers/%s/%s/photo-%s.%s', $companyId, $carrierId, Uuid::uuid7(), strtolower($extension));
        $stream = fopen($localPath, 'rb');
        try {
            $this->disk->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        return $path;
    }

    public function delete(string $path): void
    {
        $this->disk->delete($path);
    }
}
