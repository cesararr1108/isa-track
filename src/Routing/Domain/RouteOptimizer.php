<?php

declare(strict_types=1);

namespace Isa\Routing\Domain;

use Isa\Shared\Domain\ValueObject\GeoPoint;

/**
 * Ordena las paradas para recorrer menos: vecino más cercano desde la bodega
 * y luego mejora con 2-opt (deshace cruces). Suficiente para 5-60 paradas;
 * con tráfico real se puede cambiar por OSRM/Google sin tocar los casos de uso.
 */
final class RouteOptimizer
{
    /**
     * @param  list<RouteStop>  $stops
     * @return list<RouteStop>
     */
    public function optimize(GeoPoint $start, array $stops): array
    {
        if (count($stops) < 3) {
            return $this->nearestNeighbor($start, $stops);
        }

        return $this->twoOpt($start, $this->nearestNeighbor($start, $stops));
    }

    /** @param list<RouteStop> $stops */
    public static function lengthKm(GeoPoint $start, array $stops): float
    {
        $total = 0.0;
        $prev = $start;
        foreach ($stops as $stop) {
            $total += Distance::km($prev, $stop->location);
            $prev = $stop->location;
        }

        return $total;
    }

    /**
     * @param  list<RouteStop>  $stops
     * @return list<RouteStop>
     */
    private function nearestNeighbor(GeoPoint $start, array $stops): array
    {
        $pending = $stops;
        $ordered = [];
        $current = $start;
        while ($pending !== []) {
            $bestKey = null;
            $bestKm = INF;
            foreach ($pending as $key => $stop) {
                $km = Distance::km($current, $stop->location);
                if ($km < $bestKm) {
                    $bestKm = $km;
                    $bestKey = $key;
                }
            }
            $ordered[] = $pending[$bestKey];
            $current = $pending[$bestKey]->location;
            unset($pending[$bestKey]);
        }

        return $ordered;
    }

    /**
     * @param  list<RouteStop>  $route
     * @return list<RouteStop>
     */
    private function twoOpt(GeoPoint $start, array $route): array
    {
        $best = self::lengthKm($start, $route);
        $n = count($route);
        $improved = true;
        $rounds = 0;
        while ($improved && $rounds++ < 50) {
            $improved = false;
            for ($i = 0; $i < $n - 1; $i++) {
                for ($k = $i + 1; $k < $n; $k++) {
                    $candidate = array_merge(
                        array_slice($route, 0, $i),
                        array_reverse(array_slice($route, $i, $k - $i + 1)),
                        array_slice($route, $k + 1),
                    );
                    $length = self::lengthKm($start, $candidate);
                    if ($length + 1e-9 < $best) {
                        $route = $candidate;
                        $best = $length;
                        $improved = true;
                    }
                }
            }
        }

        return $route;
    }
}
