<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations\Reports;

use App\Http\Controllers\Controller;
use App\Support\Operations\Reports\IncidentReportQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class IncidentReportDocumentController extends Controller
{
    public function __invoke(Request $request, IncidentReportQuery $query): SymfonyResponse
    {
        abort_unless(Auth::user()?->isOperationalCentral(), 403);

        $data = $query->build(Auth::user(), [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'municipio_id' => $request->query('municipio_id') !== null ? (int) $request->query('municipio_id') : null,
            'nature_id' => $request->query('nature_id') !== null ? (int) $request->query('nature_id') : null,
            'modality' => $request->query('modality'),
        ]);

        return Pdf::loadView('operations.documents.reports.incident-report-pdf', [
            'data' => $data,
        ])->download('relatorio-ocorrencias-'.$data['period']['from'].'-a-'.$data['period']['to'].'.pdf');
    }
}
