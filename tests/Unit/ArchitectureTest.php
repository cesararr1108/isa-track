<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Guardián de la arquitectura hexagonal: Domain y Application no pueden
 * depender de Laravel ni de la capa de infraestructura.
 */
final class ArchitectureTest extends TestCase
{
    private const FORBIDDEN = ['Illuminate\\', 'Laravel\\', 'App\\', '\\Infrastructure\\'];

    /** @return iterable<string, array{string}> */
    public static function coreFiles(): iterable
    {
        $root = dirname(__DIR__, 2).'/src';
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root)) as $file) {
            $path = $file->getPathname();
            if (str_ends_with($path, '.php') && preg_match('#/(Domain|Application)/#', $path)) {
                yield substr($path, strlen($root) + 1) => [$path];
            }
        }
    }

    #[DataProvider('coreFiles')]
    public function test_core_does_not_depend_on_framework(string $path): void
    {
        preg_match_all('/^use\s+([^;]+);/m', (string) file_get_contents($path), $matches);

        foreach ($matches[1] as $import) {
            foreach (self::FORBIDDEN as $forbidden) {
                self::assertStringNotContainsString($forbidden, $import, "$path importa $import");
            }
        }

        $this->addToAssertionCount(1);
    }
}
