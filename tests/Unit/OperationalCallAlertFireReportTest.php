<?php

declare(strict_types=1);

use App\Domain\Operations\Enums\IncidentReportModality;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Models\Incident;
use App\Models\Municipio;
use App\Models\Nature;
use App\Models\OperationalCallAlert;
use App\Support\Operations\OperationalCallAlertFireReport;
use App\Support\Operations\OperationalCallAlertGrouper;
use Database\Seeders\FireModalitySeeder;
use Database\Seeders\OperationalDemoSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('builds report from single alert with metadata', function (): void {
    OperationalCallAlert::factory()->create([
        'latitude' => -23.55012,
        'longitude' => -46.63045,
        'caller_name' => 'Maria Silva',
        'external_reference' => 'PBX-1001',
        'metadata' => [
            'temperature' => 305.5,
            'humidity' => 42.0,
            'wind_speed' => 12.5,
            'wind_direction' => 90.0,
            'wind_direction_text' => 'E',
            'air_temperature' => 32.0,
            'rain' => 0.0,
        ],
    ]);

    $locationKey = OperationalCallAlertGrouper::locationKey(-23.55012, -46.63045);
    $report = OperationalCallAlertFireReport::fromLocationKey($locationKey);

    expect($report)->not->toBeNull()
        ->and($report['location_key'])->toBe($locationKey)
        ->and($report['alert_count'])->toBe(1)
        ->and($report['caller_name'])->toBe('Maria Silva')
        ->and($report['reference'])->toBe('PBX-1001')
        ->and($report['latest']['temperature'])->toBe(305.5)
        ->and($report['latest']['humidity'])->toBe(42.0)
        ->and($report['metrics'])->not->toBeEmpty();
});

test('detects temperature increase between two alerts', function (): void {
    OperationalCallAlert::factory()->create([
        'latitude' => -23.55012,
        'longitude' => -46.63045,
        'call_received_at' => now()->subMinutes(10),
        'metadata' => [
            'temperature' => 300.0,
            'humidity' => 40.0,
            'wind_speed' => 10.0,
        ],
    ]);

    OperationalCallAlert::factory()->create([
        'latitude' => -23.55012,
        'longitude' => -46.63045,
        'call_received_at' => now(),
        'metadata' => [
            'temperature' => 310.0,
            'humidity' => 38.0,
            'wind_speed' => 12.0,
        ],
    ]);

    $locationKey = OperationalCallAlertGrouper::locationKey(-23.55012, -46.63045);
    $report = OperationalCallAlertFireReport::fromLocationKey($locationKey);

    expect($report)->not->toBeNull()
        ->and($report['alert_count'])->toBe(2);

    $titles = array_column($report['observations'], 'title');

    expect($titles)->toContain(__('Aumento de temperatura radiativa'));
});

test('returns null for empty location key', function (): void {
    expect(OperationalCallAlertFireReport::fromLocationKey(''))->toBeNull();
});
test('builds report from incident linked alerts', function (): void {
    $this->seed(OperationalDemoSeeder::class);
    $this->seed(FireModalitySeeder::class);

    /** @var Municipio $municipio */
    $municipio = Municipio::query()->firstOrFail();

    $incident = Incident::query()->create([
        'municipio_id' => $municipio->id,
        'dispatch_year' => (int) now()->format('Y'),
        'talao' => 990000 + random_int(1, 999),
        'status' => IncidentStatus::Closed,
        'nature_id' => Nature::query()
            ->where('report_modality', IncidentReportModality::FireForest->value)
            ->value('id'),
        'occurred_at' => now(),
        'caller_phone' => '11999990001',
    ]);

    OperationalCallAlert::factory()->converted()->create([
        'converted_incident_id' => $incident->id,
        'latitude' => -23.55012,
        'longitude' => -46.63045,
        'external_reference' => 'SAT-9001',
        'metadata' => [
            'temperature' => 308.0,
            'humidity' => 35.0,
            'wind_speed' => 18.0,
            'wind_direction_text' => 'NE',
        ],
    ]);

    $report = OperationalCallAlertFireReport::fromIncident($incident);

    expect($report)->not->toBeNull()
        ->and($report['alert_count'])->toBe(1)
        ->and($report['reference'])->toBe('SAT-9001')
        ->and($report['latest']['humidity'])->toBe(35.0);
});
