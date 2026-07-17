<?php

declare(strict_types=1);

namespace App\Integrations\Wind\DTOs;

/**
 * Leitura de vento a 10 m num ponto da grade, neutra de provedor (já em km/h).
 *
 * ATENÇÃO à convenção: `directionFromDeg` é meteorológica — indica de onde o vento
 * VEM (0° = norte, 90° = leste). O fogo corre no sentido oposto, para onde o vento
 * SOPRA. Use `spreadsToDeg()` ao desenhar a seta; inverter isso aponta a brigada
 * para o lado errado.
 */
final readonly class WindReading
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public float $speedKmh,
        public float $directionFromDeg,
        public ?float $gustsKmh,
        public ?string $observedAt,
    ) {}

    /** Sentido para onde o vento sopra — é para cá que o fogo tende a correr. */
    public function spreadsToDeg(): float
    {
        return fmod($this->directionFromDeg + 180.0, 360.0);
    }

    /**
     * @return array{
     *     lat: float,
     *     lng: float,
     *     speed_kmh: float,
     *     direction_from_deg: float,
     *     spreads_to_deg: float,
     *     gusts_kmh: ?float,
     *     observed_at: ?string
     * }
     */
    public function toLeaflet(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'speed_kmh' => round($this->speedKmh, 1),
            'direction_from_deg' => round($this->directionFromDeg, 1),
            'spreads_to_deg' => round($this->spreadsToDeg(), 1),
            'gusts_kmh' => $this->gustsKmh !== null ? round($this->gustsKmh, 1) : null,
            'observed_at' => $this->observedAt,
        ];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'speed_kmh' => $this->speedKmh,
            'direction_from_deg' => $this->directionFromDeg,
            'gusts_kmh' => $this->gustsKmh,
            'observed_at' => $this->observedAt,
        ];
    }

    /** @param  array<string, mixed>  $data */
    public static function fromArray(array $data): self
    {
        return new self(
            latitude: (float) $data['latitude'],
            longitude: (float) $data['longitude'],
            speedKmh: (float) $data['speed_kmh'],
            directionFromDeg: (float) $data['direction_from_deg'],
            gustsKmh: isset($data['gusts_kmh']) ? (float) $data['gusts_kmh'] : null,
            observedAt: $data['observed_at'] !== null ? (string) $data['observed_at'] : null,
        );
    }
}
