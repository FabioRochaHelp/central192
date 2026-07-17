<?php

declare(strict_types=1);

namespace App\Support\Operations;

use App\Domain\Operations\Actions\AppendIncidentDescriptionAction;
use App\Models\IncidentDispatch;
use App\Models\IncidentEvent;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Anotações que a guarnição ainda não recebeu, por empenho.
 *
 * Uma ocorrência em atendimento continua recebendo informação — inclusive novas
 * solicitações do mesmo ponto, que anexam texto à descrição. O balão no kanban conta
 * o que entrou depois do empenho e ainda não foi aberto, para o despachador saber o
 * que falta repassar à viatura.
 */
final class DispatchNoteSignal
{
    /**
     * Anotações não vistas de cada empenho, resolvidas numa consulta só.
     *
     * @param  Collection<int, IncidentDispatch>  $dispatches
     * @return array<int, int> contagem por id do empenho (só entradas > 0)
     */
    public static function unseenCountsByDispatchId(Collection $dispatches): array
    {
        if ($dispatches->isEmpty()) {
            return [];
        }

        $incidentIds = $dispatches->pluck('incident_id')->filter()->unique()->values();

        if ($incidentIds->isEmpty()) {
            return [];
        }

        /** @var Collection<int, Collection<int, IncidentEvent>> $eventsByIncident */
        $eventsByIncident = IncidentEvent::query()
            ->select(['incident_id', 'recorded_at'])
            ->whereIn('incident_id', $incidentIds)
            ->where('event_key', AppendIncidentDescriptionAction::EVENT_KEY)
            ->get()
            ->groupBy('incident_id');

        $counts = [];

        foreach ($dispatches as $dispatch) {
            $events = $eventsByIncident->get($dispatch->incident_id);

            if ($events === null) {
                continue;
            }

            $since = self::baselineFor($dispatch);

            if ($since === null) {
                continue;
            }

            $unseen = $events
                ->filter(fn (IncidentEvent $event): bool => $event->recorded_at?->greaterThanOrEqualTo($since) ?? false)
                ->count();

            if ($unseen > 0) {
                $counts[(int) $dispatch->id] = $unseen;
            }
        }

        return $counts;
    }

    public static function unseenCountFor(IncidentDispatch $dispatch): int
    {
        return self::unseenCountsByDispatchId(collect([$dispatch]))[(int) $dispatch->id] ?? 0;
    }

    /** Marca como repassado tudo que existe até agora para este empenho. */
    public static function markSeen(IncidentDispatch $dispatch): void
    {
        $dispatch->update(['notes_seen_at' => now()]);
    }

    /**
     * Nunca aberto: conta desde o empenho, ignorando o que a guarnição já recebeu na saída.
     *
     * A comparação com o marco é inclusiva (`>=`) de propósito. `recorded_at` e `created_at`
     * têm precisão de segundo, então uma anotação escrita no mesmo segundo do clique empataria
     * com o marco: com `>` ela sumiria sem nunca ter sido mostrada. Assim o empate mantém o
     * balão aceso — no máximo pede um segundo clique, e nenhuma anotação se perde.
     */
    private static function baselineFor(IncidentDispatch $dispatch): ?CarbonInterface
    {
        return $dispatch->notes_seen_at ?? $dispatch->created_at;
    }
}
