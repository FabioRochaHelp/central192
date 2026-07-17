<?php

declare(strict_types=1);

namespace App\Livewire\Operations\Reports;

use App\Support\Operations\Reports\FireFocosReportQuery;
use Illuminate\Contracts\View\View;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use PDOException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Relatório gerencial de focos de calor (INPE). */
#[Layout('layouts.app')]
#[Title('Relatório de focos de calor')]
final class FireFocosReport extends Component
{
    #[Url]
    public string $from = '';

    #[Url]
    public string $to = '';

    #[Url]
    public string $estado = '';

    #[Url]
    public string $bioma = '';

    #[Url]
    public string $satelite = '';

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
            'estado' => $this->estado !== '' ? $this->estado : null,
            'bioma' => $this->bioma !== '' ? $this->bioma : null,
            'satelite' => $this->satelite !== '' ? $this->satelite : null,
        ];
    }

    public function exportCsv(FireFocosReportQuery $query): ?StreamedResponse
    {
        try {
            $data = $query->build(Auth::user(), $this->filters());
        } catch (QueryException|PDOException) {
            $this->addError('connection', __('Não foi possível conectar ao banco de focos satelitais.'));

            return null;
        }

        $filename = 'relatorio-focos-'.$data['period']['from'].'-a-'.$data['period']['to'].'.csv';

        return response()->streamDownload(function () use ($data): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");

            fputcsv($out, ['Relatório de focos de calor']);
            fputcsv($out, ['Período', $data['period']['from'].' a '.$data['period']['to']]);
            fputcsv($out, ['Total de focos', $data['total']]);
            fputcsv($out, []);

            foreach ([
                'Por satélite' => $data['by_satelite'],
                'Por bioma' => $data['by_bioma'],
                'Por município' => $data['by_municipio'],
            ] as $title => $rows) {
                fputcsv($out, [$title]);
                fputcsv($out, ['Categoria', 'Quantidade']);
                foreach ($rows as $row) {
                    fputcsv($out, [$row['label'], $row['count']]);
                }
                fputcsv($out, []);
            }

            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    public function render(FireFocosReportQuery $query): View
    {
        $data = null;
        $connectionError = false;

        try {
            $data = $query->build(Auth::user(), $this->filters());
        } catch (QueryException|PDOException) {
            $connectionError = true;
        }

        return view('livewire.operations.reports.fire-focos-report', [
            'data' => $data,
            'connectionError' => $connectionError,
        ]);
    }
}
