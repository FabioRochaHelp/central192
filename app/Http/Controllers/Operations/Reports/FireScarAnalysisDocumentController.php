<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations\Reports;

use App\Http\Controllers\Controller;
use App\Models\FireScarAnalysis;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class FireScarAnalysisDocumentController extends Controller
{
    public function __invoke(FireScarAnalysis $fireScarAnalysis): SymfonyResponse
    {
        abort_unless(Auth::user()?->isOperationalCentral(), 403);

        $fireScarAnalysis->load('incident:id,talao,dispatch_year');

        return Pdf::loadView('operations.documents.reports.fire-scar-analysis-pdf', [
            'analysis' => $fireScarAnalysis,
            'severity' => $fireScarAnalysis->severity(),
        ])->download(sprintf('cicatriz-incendio-%d.pdf', $fireScarAnalysis->getKey()));
    }
}
