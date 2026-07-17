<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations\Reports;

use App\Http\Controllers\Controller;
use App\Support\Operations\Reports\RegulationReportQuery;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class RegulationReportDocumentController extends Controller
{
    public function __invoke(Request $request, RegulationReportQuery $query): SymfonyResponse
    {
        abort_unless(Auth::user()?->hasOperationalAbility('regulation.view'), 403);

        $data = $query->build(Auth::user(), [
            'from' => $request->query('from'),
            'to' => $request->query('to'),
            'municipio_id' => $request->query('municipio_id') !== null ? (int) $request->query('municipio_id') : null,
            'regulator_id' => $request->query('regulator_id') !== null ? (int) $request->query('regulator_id') : null,
            'decision' => $request->query('decision'),
            'priority' => $request->query('priority'),
        ]);

        return Pdf::loadView('operations.documents.reports.regulation-report-pdf', [
            'data' => $data,
        ])->download('relatorio-regulacao-'.$data['period']['from'].'-a-'.$data['period']['to'].'.pdf');
    }
}
