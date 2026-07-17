<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use App\Support\Operations\IncidentFinalReportDocument;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class IncidentFinalReportDocumentController extends Controller
{
    public function __invoke(Request $request, Incident $incident): View|SymfonyResponse
    {
        Gate::authorize('viewFinalReportDocument', $incident);

        $document = IncidentFinalReportDocument::fromIncident($incident);

        if ($request->boolean('download')) {
            return Pdf::loadView('operations.documents.incident-final-report-pdf', [
                'document' => $document,
            ])->download($this->downloadFilename($incident));
        }

        return view('operations.documents.incident-final-report', [
            'document' => $document,
        ]);
    }

    private function downloadFilename(Incident $incident): string
    {
        return sprintf(
            'relatorio-final-%s-%s.pdf',
            $incident->talao ?? 'sem-talao',
            $incident->dispatch_year,
        );
    }
}
