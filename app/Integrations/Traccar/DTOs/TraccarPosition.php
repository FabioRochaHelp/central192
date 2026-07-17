<?php

declare(strict_types=1);

namespace App\Integrations\Traccar\DTOs;

final readonly class TraccarPosition
{
    public function __construct(
        public int $id,
        public int $deviceId,
        public string $fixTime,
        public float $latitude,
        public float $longitude,
        public float $altitude,
        public float $speed,
        public float $course,
        public string $address,
        public bool $valid,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromArray(array $data): self
    {
        return new self(
            id: (int) ($data['id'] ?? 0),
            deviceId: (int) ($data['deviceId'] ?? 0),
            fixTime: (string) ($data['fixTime'] ?? ''),
            latitude: (float) ($data['latitude'] ?? 0),
            longitude: (float) ($data['longitude'] ?? 0),
            altitude: (float) ($data['altitude'] ?? 0),
            speed: (float) ($data['speed'] ?? 0),
            course: (float) ($data['course'] ?? 0),
            address: (string) ($data['address'] ?? ''),
            valid: (bool) ($data['valid'] ?? false),
        );
    }

    /** Speed in km/h (Traccar returns knots). */
    public function speedKmh(): float
    {
        return round($this->speed * 1.852, 1);
    }
}
