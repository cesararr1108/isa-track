<?php

declare(strict_types=1);

namespace Isa\Shipping\Application\Port;

/** Archivo recibido (foto o firma) ya guardado temporalmente en disco. */
final class EvidenceFile
{
    public function __construct(
        public readonly string $localPath,
        public readonly string $extension,
    ) {}
}
