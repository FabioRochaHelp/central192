<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Localiza ocorrências ativas já registradas no mesmo ponto (ou muito perto) de uma nova chamada.
 *
 * Alimenta o aviso de duplicidade no cadastro: o operador decide entre abrir uma
 * ocorrência nova ou somar a ligação como solicitação da ocorrência existente.
 *
 * Só considera ocorrências ainda passíveis de despacho ({@see IncidentOperationalState::dispatchableStatuses()}),
 * o que naturalmente descarta encerradas, canceladas, QTA e chamadas simples. A janela de tempo
 * evita arrastar ocorrências abertas há dias, e a visibilidade por município é respeitada.
 */
final class NearbyIncidentFinder
{
    /**
     * Ocorrências dentro do raio, da mais próxima para a mais distante.
     *
     * Cada item recebe o atributo calculado `distance_meters`.
     *
     * @return Collection<int, Incident>
     */
    public static function find(float $lat, float $lng, ?User $user, ?float $radiusMeters = null): Collection
    {
        $radiusMeters ??= (float) config('operations.duplicate_incident_radius_meters');
        $windowHours = (int) config('operations.duplicate_incident_window_hours');

        $box = GeoDistance::boundingBox($lat, $lng, $radiusMeters);

        $query = Incident::query()
            ->with(['nature', 'municipio'])
            ->withCount('callRequests')
            ->whereIn('status', IncidentOperationalState::dispatchableStatuses())
            ->where('occurred_at', '>=', now()->subHours($windowHours))
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->whereBetween('latitude', [$box['min_lat'], $box['max_lat']])
            ->whereBetween('longitude', [$box['min_lng'], $box['max_lng']]);

        OperationalIncidentVisibility::constrainListing($query, $user);

        return $query->get()
            ->map(function (Incident $incident) use ($lat, $lng): Incident {
                $incident->distance_meters = (int) round(GeoDistance::haversineMeters(
                    $lat,
                    $lng,
                    (float) $incident->latitude,
                    (float) $incident->longitude,
                ));

                return $incident;
            })
            ->filter(static fn (Incident $incident): bool => $incident->distance_meters <= $radiusMeters)
            ->sortBy(static fn (Incident $incident): int => $incident->distance_meters)
            ->values();
    }
}
