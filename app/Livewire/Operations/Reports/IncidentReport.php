<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Reports;

use App\Domain\Operations\Enums\IncidentReportModality;
use App\Models\Municipio;
use App\Models\Nature;
use App\Support\Operations\Reports\IncidentReportQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Relatório gerencial/estatístico de ocorrências. */
#[Layout('layouts.app')]
#[Title('Relatório de ocorrências')]
final class IncidentReport extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public ?int $municipioId = null;

    #[Url]
    public ?int $natureId = null;

    #[Url]
    public string $modality = '';

    public function mount(): void
    {
        abort_unless(Auth::user()?->isOperationalCentral(), 403);

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
            'nature_id' => $this->natureId,
            'modality' => $this->modality !== '' ? $this->modality : null,
        ];
    }

    public function exportCsv(IncidentReportQuery $query): StreamedResponse
    {
        $data = $query->build(Auth::user(), $this->filters());

        $filename = 'relatorio-ocorrencias-'.$data['period']['from'].'-a-'.$data['period']['to'].'.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            // BOM p/ Excel reconhecer UTF-8
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Relatório de ocorrências']);
            fputcsv($out, ['Período', $data['period']['from'].' a '.$data['period']['to']]);
            fputcsv($out, ['Total de ocorrências', $data['total']]);
            fputcsv($out, []);

            fputcsv($out, ['Por modalidade']);
            fputcsv($out, ['Modalidade', 'Quantidade']);
            foreach ($data['by_modality'] as $row) {
                fputcsv($out, [$row['label'], $row['count']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Por status']);
            fputcsv($out, ['Status', 'Quantidade']);
            foreach ($data['by_status'] as $row) {
                fputcsv($out, [$row['label'], $row['count']]);
            }
            fputcsv($out, []);

            fputcsv($out, ['Por município']);
            fputcsv($out, ['Município', 'Quantidade']);
            foreach ($data['by_municipio'] as $row) {
                fputcsv($out, [$row['municipio'], $row['count']]);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render(IncidentReportQuery $query): View
    {
        $data = $query->build(Auth::user(), $this->filters());

        return view('livewire.operations.reports.incident-report', [
            'data' => $data,
            'municipios' => Municipio::query()->orderBy('city')->get(['id', 'city']),
            'natures' => Nature::query()->orderBy('name')->get(['id', 'name']),
            'modalities' => IncidentReportModality::cases(),
        ]);
    }
}
