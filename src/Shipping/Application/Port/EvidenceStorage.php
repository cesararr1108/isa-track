<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\Port;

use Isa\Shared\Domain\ValueObject\CompanyId;
use Isa\Shipping\Domain\OrderId;

/** Dónde se guardan fotos y firmas de entrega (disco local hoy, S3 mañana). */
interface EvidenceStorage
{
    /** Guarda el archivo y devuelve la ruta interna con la que se recupera después. */
    public function store(CompanyId $companyId, OrderId $orderId, string $kind, EvidenceFile $file): string;

    public function delete(string $path): void;
}
