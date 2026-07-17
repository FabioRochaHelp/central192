<?php

declare(strict_types=1);

namespace App\Integrations\Traccar\DTOs;

final readonly class TraccarRoutePoint
{
    public function __construct(
        public float $latitude,
        public float $longitude,
        public string $fixTime,
        public float $speed,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            latitude: (float) ($data['latitude'] ?? 0),
            longitude: (float) ($data['longitude'] ?? 0),
            fixTime: (string) ($data['fixTime'] ?? ''),
            speed: (float) ($data['speed'] ?? 0),
        );
    }

    /** @return array{lat: float, lng: float, time: string, speed_kmh: float} */
    public function toLeaflet(): array
    {
        return [
            'lat' => $this->latitude,
            'lng' => $this->longitude,
            'time' => $this->fixTime,
            'speed_kmh' => round($this->speed * 1.852, 1),
        ];
    }
}
