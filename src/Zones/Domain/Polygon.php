<?php

declare(strict_types=1);

namespace Isa\Zones\Domain;

use InvalidArgumentException;
use Isa\Shared\Domain\ValueObject\GeoPoint;

/**
 * Área de una zona dibujada en el mapa: lista de vértices (lat, lng) en orden.
 * Para un barrio o comuna basta la geometría plana (las distancias son cortas).
 */
final class Polygon
{
    private const MAX_VERTICES = 500;

    /** @var list<GeoPoint> */
    public readonly array $vertices;

    /** @param list<GeoPoint> $vertices */
    public function __construct(array $vertices)
    {
        $vertices = array_values($vertices);
        // Si el último vértice repite el primero (GeoJSON), se quita.
        if (count($vertices) > 1) {
            $first = $vertices[0];
            $last = $vertices[count($vertices) - 1];
            if ($first->latitude === $last->latitude && $first->longitude === $last->longitude) {
                array_pop($vertices);
            }
        }
        if (count($vertices) < 3) {
            throw new InvalidArgumentException('Una zona necesita al menos 3 puntos en el mapa.');
        }
        if (count($vertices) > self::MAX_VERTICES) {
            throw new InvalidArgumentException('La zona tiene demasiados puntos (máximo '.self::MAX_VERTICES.').');
        }
        $this->vertices = $vertices;
        if ($this->area() <= 0) {
            throw new InvalidArgumentException('La zona no puede tener área cero.');
        }
    }

    /** @param list<array{0: float|int, 1: float|int}> $pairs  [[lat, lng], ...] */
    public static function fromPairs(array $pairs): self
    {
        return new self(array_map(fn (array $p) => new GeoPoint((float) $p[0], (float) $p[1]), array_values($pairs)));
    }

    /** @return list<array{0: float, 1: float}> */
    public function toPairs(): array
    {
        return array_map(fn (GeoPoint $p) => [$p->latitude, $p->longitude], $this->vertices);
    }

    /** Algoritmo de "ray casting": ¿el punto cae dentro del polígono? (borde incluido) */
    public function contains(GeoPoint $point): bool
    {
        $x = $point->longitude;
        $y = $point->latitude;
        $inside = false;
        $n = count($this->vertices);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $xi = $this->vertices[$i]->longitude;
            $yi = $this->vertices[$i]->latitude;
            $xj = $this->vertices[$j]->longitude;
            $yj = $this->vertices[$j]->latitude;

            if ($this->onSegment($x, $y, $xi, $yi, $xj, $yj)) {
                return true;
            }
            if (($yi > $y) !== ($yj > $y) && $x < ($xj - $xi) * ($y - $yi) / ($yj - $yi) + $xi) {
                $inside = ! $inside;
            }
        }

        return $inside;
    }

    /** Área en "grados cuadrados" (sirve para comparar zonas, no para medir). */
    public function area(): float
    {
        $sum = 0.0;
        $n = count($this->vertices);
        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $sum += ($this->vertices[$j]->longitude * $this->vertices[$i]->latitude)
                - ($this->vertices[$i]->longitude * $this->vertices[$j]->latitude);
        }

        return abs($sum) / 2;
    }

    public function centroid(): GeoPoint
    {
        $lat = array_sum(array_map(fn (GeoPoint $p) => $p->latitude, $this->vertices)) / count($this->vertices);
        $lng = array_sum(array_map(fn (GeoPoint $p) => $p->longitude, $this->vertices)) / count($this->vertices);

        return new GeoPoint($lat, $lng);
    }

    private function onSegment(float $x, float $y, float $x1, float $y1, float $x2, float $y2): bool
    {
        $cross = ($x - $x1) * ($y2 - $y1) - ($y - $y1) * ($x2 - $x1);
        if (abs($cross) > 1e-12) {
            return false;
        }

        return $x >= min($x1, $x2) - 1e-12 && $x <= max($x1, $x2) + 1e-12
            && $y >= min($y1, $y2) - 1e-12 && $y <= max($y1, $y2) + 1e-12;
    }
}
