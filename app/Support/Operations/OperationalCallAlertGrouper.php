<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Domain\Operations\Enums\OperationalCallAlertStatus;
use App\Models\OperationalCallAlert;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/** Agrupa alertas ativos no mapa tático pela mesma coordenada (precisão ~1 m). */
final class OperationalCallAlertGrouper
{
    public static function locationKey(float $lat, float $lng): string
    {
        return round($lat, 5).','.round($lng, 5);
    }

    /** @return Builder<OperationalCallAlert> */
    public static function activeOnMapQuery(): Builder
    {
        return OperationalCallAlert::query()
            ->where('status', OperationalCallAlertStatus::Pending)
            ->where('expires_at', '>', now());
    }

    /** @return Collection<int, OperationalCallAlert> */
    public static function activeAtLocationKey(string $locationKey): Collection
    {
        return self::activeOnMapQuery()
            ->orderByDesc('created_at')
            ->get()
            ->filter(
                fn (OperationalCallAlert $alert): bool => self::locationKey(
                    (float) $alert->latitude,
                    (float) $alert->longitude,
                ) === $locationKey,
            )
            ->values();
    }

    public static function latestActiveAtLocationKey(string $locationKey): ?OperationalCallAlert
    {
        return self::activeAtLocationKey($locationKey)->first();
    }

    /** @return Collection<int, OperationalCallAlert> */
    public static function activeAtLocation(float $lat, float $lng): Collection
    {
        return self::activeAtLocationKey(self::locationKey($lat, $lng));
    }

    /**
     * @param  Collection<int, OperationalCallAlert>  $alerts
     * @return array<string, mixed>|null
     */
    public static function clusterFromAlerts(Collection $alerts): ?array
    {
        if ($alerts->isEmpty()) {
            return null;
        }

        /** @var OperationalCallAlert $first */
        $first = $alerts->first();
        $lat = (float) $first->latitude;
        $lng = (float) $first->longitude;

        $count = $alerts->count();

        return [
            'location_key' => self::locationKey($lat, $lng),
            'lat' => $lat,
            'lng' => $lng,
            'count' => $count,
            'monitoring' => $count > 1,
            'alerts' => $alerts
                ->map(fn (OperationalCallAlert $a): array => $a->toMapPayload())
                ->values()
                ->all(),
        ];
    }

    /** @return array<string, mixed>|null */
    public static function clusterForLocation(float $lat, float $lng): ?array
    {
        return self::clusterFromAlerts(self::activeAtLocation($lat, $lng));
    }

    /** @return list<array<string, mixed>> */
    public static function allClusters(): array
    {
        $grouped = self::activeOnMapQuery()
            ->orderByDesc('created_at')
            ->get()
            ->groupBy(
                fn (OperationalCallAlert $alert): string => self::locationKey(
                    (float) $alert->latitude,
                    (float) $alert->longitude,
                ),
            );

        return $grouped
            ->map(fn (Collection $alerts): ?array => self::clusterFromAlerts($alerts))
            ->filter()
            ->values()
            ->all();
    }
}
