<?php

declare(strict_types=1);

namespace Isa\Cash\Domain\Exception;

use Isa\Shared\Domain\DomainException;

final class DifferenceRequiresNote extends DomainException
{
    public function __construct(int $difference)
    {
        parent::__construct(sprintf('Hay una diferencia de $%s; escribe una nota explicándola.', number_format($difference, 0, ',', '.')));
    }

    public function errorCode(): string
    {
        return 'difference_requires_note';
    }
}
