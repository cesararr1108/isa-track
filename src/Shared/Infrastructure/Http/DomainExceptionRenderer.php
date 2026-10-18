<?php

declare(strict_types=1);

namespace Isa\Shared\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use InvalidArgumentException;
use Isa\Shared\Domain\DomainException;
use Isa\Shared\Domain\NotFoundException;

/** Traduce las excepciones del dominio a respuestas HTTP JSON. */
final class DomainExceptionRenderer
{
    public function render(DomainException $e): JsonResponse
    {
        return new JsonResponse(
            ['error' => $e->errorCode(), 'message' => $e->getMessage()],
            $e instanceof NotFoundException ? 404 : 422,
        );
    }

    public function renderInvalidArgument(InvalidArgumentException $e): JsonResponse
    {
        return new JsonResponse(['error' => 'invalid_argument', 'message' => $e->getMessage()], 422);
    }
}
