<?php

declare(strict_types=1);

namespace Isa\Companies\Domain;

use InvalidArgumentException;

/** Marca blanca de la empresa: logo y colores para la página de rastreo. */
final class Branding
{
    public function __construct(
        public readonly ?string $logoUrl,
        public readonly string $primaryColor,
        public readonly string $secondaryColor,
    ) {
        foreach ([$primaryColor, $secondaryColor] as $color) {
            if (! preg_match('/^#[0-9a-fA-F]{6}$/', $color)) {
                throw new InvalidArgumentException(sprintf('El color <%s> debe tener formato #RRGGBB.', $color));
            }
        }
    }
}
