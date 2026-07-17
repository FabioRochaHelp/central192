<?php

declare(strict_types=1);

namespace App\Integrations\Tracking\DTOs;

/**
 * Posição atual de uma unidade rastreada, neutra de provedor.
 * `unitReference` é a chave que liga a posição a um Vehicle
 * (device_id no Traccar, TrackedUnitIntegrationCode no SSX).
 */
final readonly class TrackingPosition
{
    public function __construct(
        public string $unitReference,
        public string $fixTime,
        public float $latitude,
        public float $longitude,
        public float $altitude,
        public float $speedKmh,
        public float $course,
        public string $address,
        public bool $valid,
        public bool $ignition = false,
    ) {}
}
