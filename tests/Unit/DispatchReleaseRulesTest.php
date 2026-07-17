<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\DispatchStage;
use App\Domain\Operations\Enums\IncidentReportModality;
use App\Models\Incident;
use App\Models\IncidentDispatch;
use App\Models\Nature;
use App\Support\Operations\DispatchReleaseRules;
use Tests\TestCase;

uses(TestCase::class);

test('can release vehicle at released hospital stage', function (): void {
    $incident = Incident::make(['patient_call_type' => 'N']);
    $incident->setRelation('nature', Nature::make(['report_modality' => IncidentReportModality::Samu]));

    $dispatch = IncidentDispatch::make(['stage' => DispatchStage::ReleasedHospital]);
    $dispatch->setRelation('shift', (object) ['vehicle_id' => 1]);

    expect(DispatchReleaseRules::canReleaseVehicle($dispatch, $incident))->toBeTrue();
});

test('requires left scene release for cb modalities', function (): void {
    $incident = Incident::make();
    $incident->setRelation('nature', Nature::make(['report_modality' => IncidentReportModality::FireForest]));

    $atLeftScene = IncidentDispatch::make(['stage' => DispatchStage::LeftScene]);
    $atLeftScene->setRelation('shift', (object) ['vehicle_id' => 1]);

    $atHospital = IncidentDispatch::make(['stage' => DispatchStage::ReleasedHospital]);
    $atHospital->setRelation('shift', (object) ['vehicle_id' => 1]);

    expect(DispatchReleaseRules::canReleaseVehicle($atLeftScene, $incident))->toBeTrue()
        ->and(DispatchReleaseRules::canReleaseVehicle($atHospital, $incident))->toBeTrue();
});
