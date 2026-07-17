<?php

declare(strict_types=1);

namespace App\Livewire\Operations;

use App\Domain\Operations\Enums\OperationalCallAlertStatus;
use App\Domain\Operations\Events\OperationalCallAlertClusterUpdated;
use App\Models\OperationalCallAlert;
use App\Support\Operations\OperationalCallAlertFireReport;
use App\Support\Operations\OperationalCallAlertGrouper;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\On;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Layout('layouts.app')]
#[Title('Alertas pendentes')]
final class CallAlertIndex extends Component
{
    /** Reverb: novo alerta, cluster atualizado ou abortado — re-renderiza a lista. */
    #[On('call-alert-index-refresh')]
    public function refreshFromBroadcast(): void {}

    public function abortAlert(string $locationKey): void
    {
        $user = Auth::user();
        if ($user === null || ! $user->hasOperationalAbility('incident.create')) {
            return;
        }

        $alerts = OperationalCallAlertGrouper::activeAtLocationKey($locationKey);
        if ($alerts->isEmpty()) {
            return;
        }

        $lat = (float) $alerts->first()->latitude;
        $lng = (float) $alerts->first()->longitude;

        foreach ($alerts as $alert) {
            if (! $alert->isActionable()) {
                continue;
            }

            $alert->update([
                'status' => OperationalCallAlertStatus::Aborted,
                'aborted_by' => $user->id,
                'aborted_at' => now(),
            ]);
        }

        OperationalCallAlertClusterUpdated::dispatchForLocation($lat, $lng);
    }

    public function createIncidentFromAlert(string $alertId): void
    {
        $user = Auth::user();
        if ($user === null || ! $user->hasOperationalAbility('incident.create')) {
            return;
        }

        $alert = OperationalCallAlert::query()->find($alertId);
        if ($alert === null || ! $alert->isActionable()) {
            return;
        }

        $this->dispatch('operational-call-intake', ...$alert->toIntakePrefill());
    }

    public function openFireReport(string $locationKey): void
    {
        $user = Auth::user();
        if ($user === null || ! $user->hasOperationalAbility('dispatch.view')) {
            return;
        }

        $this->dispatch('call-alert-fire-report', locationKey: $locationKey);
    }

    public function render(): View
    {
        abort_unless(Auth::user()?->hasOperationalAbility('dispatch.view'), 403);

        $alerts = OperationalCallAlertGrouper::activeOnMapQuery()
            ->orderByDesc('call_received_at')
            ->orderByDesc('created_at')
            ->get();

        $grouped = $alerts->groupBy(
            fn (OperationalCallAlert $alert): string => OperationalCallAlertGrouper::locationKey(
                (float) $alert->latitude,
                (float) $alert->longitude,
            ),
        );

        $rows = $grouped
            ->map(function (Collection $locationAlerts, string $locationKey): array {
                $stacked = $locationAlerts->sortByDesc(
                    fn (OperationalCallAlert $a) => $a->call_received_at ?? $a->created_at,
                );

                /** @var OperationalCallAlert $alert */
                $alert = $stacked->first();
                $report = OperationalCallAlertFireReport::fromAlerts($locationKey, $locationAlerts->values());

                return [
                    'alert' => $alert,
                    'location_key' => $locationKey,
                    'stacked_count' => $locationAlerts->count(),
                    'risk' => $report['risk'],
                ];
            })
            ->sortByDesc(fn (array $row) => $row['alert']->call_received_at ?? $row['alert']->created_at)
            ->values();

        $summary = [
            'total_alerts' => $alerts->count(),
            'unique_locations' => $grouped->count(),
            'high_risk_locations' => $rows
                ->filter(fn (array $row): bool => in_array($row['risk']['level'] ?? '', ['high', 'critical'], true))
                ->count(),
        ];

        return view('livewire.operations.call-alert-index', [
            'rows' => $rows,
            'summary' => $summary,
        ]);
    }
}
