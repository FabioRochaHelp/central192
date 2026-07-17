<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domain\Operations\Enums\OperationalCallAlertStatus;
use App\Models\OperationalCallAlert;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;

/**
 * @extends Factory<OperationalCallAlert>
 */
class OperationalCallAlertFactory extends Factory
{
    protected $model = OperationalCallAlert::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        $expiresAt = now()->addDays((int) config('operations.call_alert_ttl_days', 3));

        return [
            'id' => (string) Str::uuid(),
            'phone' => '11987654321',
            'caller_name' => fake()->name(),
            'latitude' => fake()->latitude(-24, -22),
            'longitude' => fake()->longitude(-47, -45),
            'call_received_at' => now(),
            'external_reference' => 'PBX-'.fake()->numerify('####'),
            'metadata' => [
                'temperature' => fake()->randomFloat(2, 20, 500),
                'humidity' => fake()->randomFloat(2, 0, 100),
                'wind_speed' => fake()->randomFloat(2, 0, 80),
                'wind_direction' => fake()->randomFloat(2, 0, 360),
                'wind_direction_text' => fake()->randomElement(['N', 'NE', 'E', 'SE', 'S', 'SW', 'W', 'NW']),
                'air_temperature' => fake()->randomFloat(2, 10, 45),
                'rain' => fake()->randomFloat(2, 0, 50),
            ],
            'form_url' => URL::temporarySignedRoute('operations.incidents.create', $expiresAt, [
                'phone' => '11987654321',
            ]),
            'expires_at' => $expiresAt,
            'status' => OperationalCallAlertStatus::Pending,
        ];
    }

    public function aborted(): static
    {
        return $this->state(fn (): array => [
            'status' => OperationalCallAlertStatus::Aborted,
            'aborted_at' => now(),
        ]);
    }

    public function monitoring(): static
    {
        return $this->state(fn (): array => [
            'status' => OperationalCallAlertStatus::Monitoring,
            'monitored_at' => now(),
        ]);
    }

    public function converted(): static
    {
        return $this->state(fn (): array => [
            'status' => OperationalCallAlertStatus::Converted,
        ]);
    }
}
