<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Reports;

use App\Domain\Operations\Enums\ManchesterRisk;
use App\Domain\Operations\Enums\RegulationDecision;
use App\Domain\Operations\Enums\UserLegacyProfile;
use App\Models\Municipio;
use App\Models\User;
use App\Support\Operations\Reports\RegulationReportQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Relatório gerencial da regulação médica (indicadores SAMU). */
#[Layout('layouts.app')]
#[Title('Relatório de regulação')]
final class RegulationReport extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $municipioId = null;

    #[Url]
    public ?int $regulatorId = null;

    #[Url]
    public string $decision = '';

    #[Url]
    public string $priority = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->hasOperationalAbility('regulation.view'), 403);

        if ($this->to === '') {
            $this->to = now()->toDateString();
        }
        if ($this->from === '') {
            $this->from = now()->subDays(30)->toDateString();
        }
    }

    /** @return array<string,mixed> */
    private function filters(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'municipio_id' => $this->municipioId,
            'regulator_id' => $this->regulatorId,
            'decision' => $this->decision !== '' ? $this->decision : null,
            'priority' => $this->priority !== '' ? $this->priority : null,
        ];
    }

    public function exportCsv(RegulationReportQuery $query): StreamedResponse
    {
        $data = $query->build(Auth::user(), $this->filters());

        $filename = 'relatorio-regulacao-'.$data['period']['from'].'-a-'.$data['period']['to'].'.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Relatório de regulação médica']);
            fputcsv($out, ['Período', $data['period']['from'].' a '.$data['period']['to']]);
            fputcsv($out, ['Total de regulações', $data['total']]);
            fputcsv($out, ['Tempo médio de regulação (min)', $data['response_time']['avg_response_min'] ?? '—']);
            fputcsv($out, ['% resolvido por orientação', $data['guidance_pct'] ?? '—']);
            fputcsv($out, []);

            fputcsv($out, ['Por decisão']);
            fputcsv($out, ['Decisão', 'Quantidade']);
            foreach ($data['by_decision'] as $row) {
                fputcsv($out, [$row['label'], $row['count']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Por prioridade']);
            fputcsv($out, ['Prioridade', 'Quantidade']);
            foreach ($data['by_priority'] as $row) {
                fputcsv($out, [$row['label'], $row['count']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Por médico regulador']);
            fputcsv($out, ['Médico', 'Regulações', 'Tempo médio (min)']);
            foreach ($data['by_regulator'] as $row) {
                fputcsv($out, [$row['name'], $row['count'], $row['avg_response_min'] ?? '—']);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render(RegulationReportQuery $query): View
    {
        $data = $query->build(Auth::user(), $this->filters());

        return view('livewire.operations.reports.regulation-report', [
            'data' => $data,
            'municipios' => Municipio::query()->orderBy('city')->get(['id', 'city']),
            'regulators' => User::query()
                ->where('users_type_legacy', UserLegacyProfile::Doctor->value)
                ->orderBy('name')
                ->get(['id', 'name']),
            'decisions' => RegulationDecision::cases(),
            'priorities' => ManchesterRisk::cases(),
        ]);
    }
}
