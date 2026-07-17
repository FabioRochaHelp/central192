<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

/** Ficha de regulação médica (PDF) de uma ocorrência. */
final class IncidentRegulationDocumentController extends Controller
{
    public function __invoke(Incident $incident): SymfonyResponse
    {
        Gate::authorize('viewRegulation', $incident);

        $incident->load(['nature', 'municipio', 'regulation.regulator']);

        abort_if($incident->regulation === null || $incident->regulation->decided_at === null, 404);

        return Pdf::loadView('operations.documents.incident-regulation-pdf', [
            'incident' => $incident,
            'regulation' => $incident->regulation,
            'generatedBy' => Auth::user(),
        ])->download('ficha-regulacao-'.$incident->talao.'-'.$incident->dispatch_year.'.pdf');
    }
}
