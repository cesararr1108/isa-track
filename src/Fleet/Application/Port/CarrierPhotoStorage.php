<?php

declare(strict_types=1);

namespace Isa\Fleet\Application\Port;

use Isa\Shared\Domain\ValueObject\CarrierId;
use Isa\Shared\Domain\ValueObject\CompanyId;

/** Dónde se guarda la foto del mensajero (disco local hoy, S3 mañana). */
interface CarrierPhotoStorage
{
    /** Guarda el archivo local y devuelve la ruta interna. */
    public function store(CompanyId $companyId, CarrierId $carrierId, string $localPath, string $extension): string;

    public function delete(string $path): void;
}
