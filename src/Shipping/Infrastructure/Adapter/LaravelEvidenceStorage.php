<?php

declare(strict_types=1);

namespace Isa\Shipping\Infrastructure\Adapter;

use Illuminate\Contracts\Filesystem\Filesystem;
use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shipping\Application\Port\EvidenceFile;
use Isa\Shipping\Application\Port\EvidenceStorage;
use Isa\Shipping\Domain\OrderId;
use Ramsey\Uuid\Uuid;

/**
 * Guarda la evidencia en un "disco" de Laravel (config/filesystems.php).
 * Con ISA_EVIDENCE_DISK=s3 queda en S3 sin cambiar código.
 * Ruta: evidence/{empresa}/{pedido}/{tipo}-{uuid}.{ext}
 */
final class LaravelEvidenceStorage implements EvidenceStorage
{
    public function __construct(private readonly Filesystem $disk) {}

    public function store(CompanyId $companyId, OrderId $orderId, string $kind, EvidenceFile $file): string
    {
        $path = sprintf('evidence/%s/%s/%s-%s.%s', $companyId, $orderId, $kind, Uuid::uuid7(), strtolower($file->extension));

        $stream = fopen($file->localPath, 'rb');
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
