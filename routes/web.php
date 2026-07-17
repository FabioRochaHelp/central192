<?php

declare(strict_types=1);

use App\Http\Controllers\Dashboard\MapFocosController;
use App\Http\Controllers\Operations\IbgeMunicipioGeoJsonController;
use App\Http\Controllers\Operations\IncidentCallIntakeWebhookController;
use App\Http\Controllers\Operations\IncidentFinalReportDocumentController;
use App\Http\Controllers\Operations\IncidentNearestVehicleController;
use App\Http\Controllers\Operations\IncidentRegulationDocumentController;
use App\Http\Controllers\Operations\IncidentRouteController;
use App\Http\Controllers\Operations\MapVehiclesController;
use App\Http\Controllers\Operations\MapWindController;
use App\Http\Controllers\Operations\MunicipiosGeoJsonController;
use App\Http\Controllers\Operations\PlayIncidentRecordingController;
use App\Http\Controllers\Operations\Reports\FireScarAnalysisDocumentController;
use App\Http\Controllers\Operations\Reports\IncidentReportDocumentController;
use App\Http\Controllers\Operations\Reports\RegulationReportDocumentController;
use App\Http\Controllers\ScreenLockController;
use App\Livewire\Dashboard;
use App\Livewire\FireFocosMap;
use App\Livewire\Operations\Admin\SystemUserManage;
use App\Livewire\Operations\Cadastro\MunicipioManage;
use App\Livewire\Operations\Cadastro\ShiftManage;
use App\Livewire\Operations\Cadastro\VehicleChecklistResourceManage;
use App\Livewire\Operations\CallAlertIndex;
use App\Livewire\Operations\DispatchBoard;
use App\Livewire\Operations\Fire\FireScarAnalysisManage;
use App\Livewire\Operations\Fire\FireScarAnalysisShow;
use App\Livewire\Operations\FleetShifts;
use App\Livewire\Operations\IncidentCallStart;
use App\Livewire\Operations\IncidentCreate;
use App\Livewire\Operations\IncidentFinalReport;
use App\Livewire\Operations\IncidentIndex;
use App\Livewire\Operations\IncidentNurseReport;
use App\Livewire\Operations\IncidentOperationalDetail;
use App\Livewire\Operations\IncidentRouteMap;
use App\Livewire\Operations\Parameters\AccessoryParameterManage;
use App\Livewire\Operations\Parameters\CareLocalParameterManage;
use App\Livewire\Operations\Parameters\HealthUnitParameterManage;
use App\Livewire\Operations\Parameters\InjurySiteParameterManage;
use App\Livewire\Operations\Parameters\NatureParameterManage;
use App\Livewire\Operations\Parameters\OperationalSupportParameterManage;
use App\Livewire\Operations\Parameters\ProcedureParameterManage;
use App\Livewire\Operations\Parameters\VictimTypeParameterManage;
use App\Livewire\Operations\Regulation\RegulationForm;
use App\Livewire\Operations\Regulation\RegulationQueue;
use App\Livewire\Operations\Reports\FireFocosReport;
use App\Livewire\Operations\Reports\IncidentReport;
use App\Livewire\Operations\Reports\RegulationReport;
use App\Livewire\Operations\StaffManage;
use App\Livewire\Operations\TacticalMap;
use App\Livewire\Operations\VehicleManage;
use App\Livewire\Operations\VictimRecord;
use App\Livewire\Operations\Victims\PrescriptionApproval;
use App\Livewire\Operations\Victims\PrescriptionForm;
use Illuminate\Support\Facades\Route;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('dashboard')
    : redirect()->route('login'))->name('home');

Route::get('integrations/calls/incident-intake', [IncidentCallIntakeWebhookController::class, 'ping'])
    ->name('integrations.calls.incident-intake.ping');

Route::post('integrations/calls/incident-intake', IncidentCallIntakeWebhookController::class)
    ->name('integrations.calls.incident-intake');

Route::middleware(['operational.tenant'])
    ->prefix('operations')
    ->group(function (): void {
        Route::get('/incidents/create', IncidentCreate::class)->name('operations.incidents.create');
        Route::view('/incidents/registered', 'operations.guest-incident-registered')->name('operations.incidents.registered-guest');
    });

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', Dashboard::class)->name('dashboard');
    Route::get('dashboard/fire-map', FireFocosMap::class)->name('dashboard.fire-map');
    Route::get('dashboard/map/focos', MapFocosController::class)->name('dashboard.map.focos');

    Route::middleware(['operational.tenant', 'operational.central'])
        ->prefix('operations/parameters')
        ->group(function (): void {
            Route::get('/acessorios', AccessoryParameterManage::class)->name('operations.parameters.accessories');
            Route::get('/apoios', OperationalSupportParameterManage::class)->name('operations.parameters.operational-supports');
            Route::get('/locais', CareLocalParameterManage::class)->name('operations.parameters.care-locals');
            Route::get('/locais-ferimento', InjurySiteParameterManage::class)->name('operations.parameters.injury-sites');
            Route::get('/naturezas', NatureParameterManage::class)->name('operations.parameters.natures');
            Route::get('/procedimentos', ProcedureParameterManage::class)->name('operations.parameters.procedures');
            Route::get('/tipos-vitima', VictimTypeParameterManage::class)->name('operations.parameters.victim-types');
            Route::get('/unidades-atendimento', HealthUnitParameterManage::class)->name('operations.parameters.health-units');
            Route::get('/checklist-recursos', VehicleChecklistResourceManage::class)->name('operations.parameters.checklist-resources');
        });

    Route::middleware(['operational.tenant', 'operational.central'])
        ->prefix('operations/cadastro')
        ->group(function (): void {
            Route::get('/bases', MunicipioManage::class)->name('operations.cadastro.bases');
        });

    Route::middleware(['operational.tenant', 'operational.central'])
        ->prefix('operations/relatorios')
        ->group(function (): void {
            Route::get('/ocorrencias', IncidentReport::class)->name('operations.reports.incidents.index');
            Route::get('/ocorrencias/document', IncidentReportDocumentController::class)->name('operations.reports.incidents.document');
            Route::get('/focos', FireFocosReport::class)->name('operations.reports.focos.index');
            Route::get('/cicatrizes', FireScarAnalysisManage::class)->name('operations.reports.fire-scars.index');
            Route::get('/cicatrizes/{analysis}', FireScarAnalysisShow::class)->name('operations.reports.fire-scars.show');
            Route::get('/cicatrizes/{fireScarAnalysis}/document', FireScarAnalysisDocumentController::class)->name('operations.reports.fire-scars.document');
        });

    // Relatório de regulação: acessível ao médico regulador (regulation.view), não só à central.
    Route::middleware(['operational.tenant'])
        ->prefix('operations/relatorios')
        ->group(function (): void {
            Route::get('/regulacao', RegulationReport::class)->name('operations.reports.regulation.index');
            Route::get('/regulacao/document', RegulationReportDocumentController::class)->name('operations.reports.regulation.document');
        });

    Route::middleware(['operational.tenant'])
        ->prefix('operations/cadastro')
        ->group(function (): void {
            Route::get('/viaturas', VehicleManage::class)->name('operations.cadastro.vehicles');
            Route::get('/efetivo', StaffManage::class)->name('operations.cadastro.staff');
            Route::get('/turnos', ShiftManage::class)->name('operations.cadastro.shifts');
        });

    Route::middleware(['operational.tenant'])
        ->prefix('operations/admin')
        ->group(function (): void {
            Route::get('/usuarios', SystemUserManage::class)->name('operations.admin.users');
        });

    Route::middleware(['operational.tenant'])
        ->prefix('operations')
        ->group(function (): void {
            Route::redirect('catalog/vehicles', '/operations/cadastro/viaturas')->name('operations.catalog.vehicles');
            Route::redirect('catalog/staff', '/operations/cadastro/efetivo')->name('operations.catalog.staff');

            Route::get('/dispatch', DispatchBoard::class)->name('operations.dispatch');
            Route::get('/tactical-map', TacticalMap::class)->name('operations.tactical-map');
            Route::get('/call-alerts', CallAlertIndex::class)->name('operations.call-alerts.index');
            Route::get('/incidents', IncidentIndex::class)->name('operations.incidents.index');
            Route::get('/incidents/start', IncidentCallStart::class)->name('operations.incidents.start');
            Route::get('/incidents/{incident}/victims/create', VictimRecord::class)->name('operations.incidents.victims.create');
            Route::get('/incidents/{incident}/victims/{victim}/edit', VictimRecord::class)->name('operations.incidents.victims.edit');
            Route::get('/victims/{victim}/prescriptions/create', PrescriptionForm::class)->name('operations.victims.prescriptions.create');
            Route::get('/prescriptions/{prescription}/approval', PrescriptionApproval::class)->name('operations.prescriptions.approval');
            Route::get('/regulation', RegulationQueue::class)->name('operations.incidents.regulation.index');
            Route::get('/incidents/{incident}/regulation', RegulationForm::class)->name('operations.incidents.regulation');
            Route::get('/incidents/{incident}/regulation/document', IncidentRegulationDocumentController::class)->name('operations.incidents.regulation.document');
            Route::get('/incidents/{incident}/nurse-report', IncidentNurseReport::class)->name('operations.incidents.nurse-report');
            Route::get('/incidents/{incident}/final-report', IncidentFinalReport::class)->name('operations.incidents.final-report');
            Route::get('/incidents/{incident}/final-report/document', IncidentFinalReportDocumentController::class)->name('operations.incidents.final-report.document');
            Route::get('/map/vehicles', MapVehiclesController::class)->name('operations.map.vehicles');
            Route::get('/map/wind', MapWindController::class)->name('operations.map.wind');
            Route::get('/cadastro/bases/ibge/{ibgeMunicipio}/geojson', IbgeMunicipioGeoJsonController::class)->name('operations.cadastro.ibge.geojson');
            Route::get('/cadastro/bases/geojson', MunicipiosGeoJsonController::class)->name('operations.cadastro.bases.geojson');
            Route::get('/incidents/{incident}/route', IncidentRouteController::class)->name('operations.incidents.route');
            Route::get('/incidents/{incident}/nearest-vehicle', IncidentNearestVehicleController::class)->name('operations.incidents.nearest-vehicle');
            Route::get('/incidents/{incident}/route-map', IncidentRouteMap::class)->name('operations.incidents.route-map');
            Route::get('/incidents/{incident}/recording', PlayIncidentRecordingController::class)->name('operations.incidents.recording');
            Route::get('/incidents/{incident}', IncidentOperationalDetail::class)->name('operations.incidents.show');
            Route::get('/fleet', FleetShifts::class)->name('operations.fleet');
        });
});

Route::middleware(['auth'])->group(function (): void {
    Route::get('area-segura', [ScreenLockController::class, 'show'])->name('screen-lock.show');
    Route::post('area-segura/desbloquear', [ScreenLockController::class, 'unlock'])
        ->middleware('throttle:screen-lock-unlock')
        ->name('screen-lock.unlock');
    Route::post('area-segura/bloquear', [ScreenLockController::class, 'store'])->name('screen-lock.store');
});

require __DIR__.'/settings.php';
