<?php

declare(strict_types=1);

namespace App\Integrations\Tracking\DTOs;

/** Ponto de percurso histórico, neutro de provedor (já em km/h). */
final readonly class TrackingRoutePoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public string $fixTime,
        public float $speedKmh,
    ) {}

    /** @return array{lat: float, lng: float, time: string, speed_kmh: float} */
    public function toLeaflet(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'time' => $this->fixTime,
            'speed_kmh' => round($this->speedKmh, 1),
        ];
    }
}
