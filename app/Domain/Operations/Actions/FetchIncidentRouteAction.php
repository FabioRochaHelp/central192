<?php

declare(strict_types=1);

namespace App\Domain\Operations\Actions;

use App\Integrations\Tracking\Contracts\TrackingProvider;
use App\Integrations\Tracking\DTOs\TrackingRoutePoint;
use App\Integrations\Tracking\Exceptions\TrackingException;
use App\Models\Incident;
use App\Support\Operations\IncidentRouteContext;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Collection;
use RuntimeException;

final class FetchIncidentRouteAction
{
    public function __construct(private readonly TrackingProvider $provider) {}

    /**
     * Retorna os pontos históricos de percurso de uma ocorrência para replay no mapa.
     *
     * @return Collection<int, TrackingRoutePoint>
     *
     * @throws RuntimeException se a viatura não tiver vínculo de rastreio ou intervalo incompleto
     */
    public function execute(Incident $incident): Collection
    {
        $route = IncidentRouteContext::for($incident);

        if ($route->from === null) {
            throw new RuntimeException(__('Ocorrência sem hora de empenho registrada.'));
        }

        if ($route->dispatch === null) {
            throw new RuntimeException(__('Ocorrência sem despacho registrado.'));
        }

        $unitReference = $route->vehicle !== null
            ? $this->provider->unitReferenceFor($route->vehicle)
            : null;

        if ($unitReference === null) {
            throw new RuntimeException(__('Viatura sem vínculo com o rastreador.'));
        }

        if ($route->to === null) {
            throw new RuntimeException(__('Ocorrência sem hora de retorno registrada para consultar o percurso.'));
        }

        try {
            return $this->provider->route($unitReference, $route->from, $route->to);
        } catch (TrackingException $e) {
            throw new RuntimeException($this->resolveTrackingMessage($e));
        } catch (ConnectionException) {
            throw new RuntimeException(__('Serviço de rastreamento indisponível ou tempo de resposta esgotado.'));
        }
    }

    private function resolveTrackingMessage(TrackingException $exception): string
    {
        if ($exception->isUnauthorized()) {
            return (string) __('Não foi possível autenticar no serviço de rastreamento.');
        }

        if ($exception->isTimeout()) {
            return (string) __('Serviço de rastreamento indisponível ou tempo de resposta esgotado.');
        }

        if ($exception->statusCode === 400) {
            return (string) __('Intervalo de percurso inválido para consulta no rastreador.');
        }

        if ($exception->statusCode === 403) {
            return (string) __('Usuário do rastreador sem permissão para relatórios de rota.');
        }

        if ($exception->statusCode === 404) {
            return (string) __('Unidade rastreada não encontrada para esta viatura.');
        }

        return (string) __('Erro ao consultar rota no serviço de rastreamento.');
    }
}
