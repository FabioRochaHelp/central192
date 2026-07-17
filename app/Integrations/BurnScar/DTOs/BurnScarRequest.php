<?php

declare(strict_types=1);

namespace App\Integrations\BurnScar\DTOs;

use App\Models\FireScarAnalysis;

/**
 * Payload de requisição enviado ao serviço externo de cicatriz.
 *
 * Espelha a seção de entrada do esquema JSON da skill (location / period /
 * satellite_source). A saída (indices/burn_severity/burned_area) volta em {@see BurnScarResult}.
 *
 * @see .agents/skills/wildfire-scar-analysis/SKILL.md
 */
final readonly class BurnScarRequest
{
    /**
     * @param  array<int,string>|null  $bandsUsed
     */
    public function __construct(
        public float $lat,
        public float $lon,
        public ?string $municipio,
        public ?string $estado,
        public ?string $bioma,
        public ?string $preFireDate,
        public ?string $postFireDate,
        public ?string $sensor,
        public ?array $bandsUsed,
        public ?int $resolutionM,
        public ?string $analysisId = null,
    ) {}

    public static function fromModel(FireScarAnalysis $analysis): self
    {
        return new self(
            lat: (float) $analysis->latitude,
            lon: (float) $analysis->longitude,
            municipio: $analysis->municipio,
            estado: $analysis->estado,
            bioma: $analysis->bioma,
            preFireDate: $analysis->pre_fire_date?->toDateString(),
            postFireDate: $analysis->post_fire_date?->toDateString(),
            sensor: $analysis->sensor,
            bandsUsed: $analysis->bands_used,
            resolutionM: $analysis->resolution_m,
            analysisId: (string) $analysis->getKey(),
        );
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'analysis_id' => $this->analysisId,
            'location' => [
                'lat' => $this->lat,
                'lon' => $this->lon,
                'municipio' => $this->municipio,
                'estado' => $this->estado,
                'bioma' => $this->bioma,
            ],
            'period' => [
                'pre_fire_date' => $this->preFireDate,
                'post_fire_date' => $this->postFireDate,
            ],
            'satellite_source' => [
                'sensor' => $this->sensor,
                'bands_used' => $this->bandsUsed,
                'resolution_m' => $this->resolutionM,
            ],
        ];
    }
}
