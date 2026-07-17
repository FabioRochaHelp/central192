<?php

declare(strict_types=1);

namespace App\Integrations\BurnScar\DTOs;

use App\Models\FireScarAnalysis;

/**
 * Resultado devolvido pelo serviço externo de cicatriz — mapeia o esquema de saída
 * JSON da skill para colunas de {@see FireScarAnalysis}.
 *
 * @see .agents/skills/wildfire-scar-analysis/SKILL.md — "Esquema de saída JSON"
 */
final readonly class BurnScarResult
{
    /**
     * @param  array<string,mixed>|null  $geometryGeojson
     */
    public function __construct(
        public ?float $nbrPre,
        public ?float $nbrPost,
        public ?float $dnbr,
        public ?float $rbr,
        public ?float $ndviPre,
        public ?float $ndviPost,
        public ?float $bai,
        public ?string $severityClass,
        public ?string $dnbrRange,
        public ?string $confidence,
        public ?float $areaHa,
        public ?float $perimeterKm,
        public ?array $geometryGeojson,
        public ?string $vegetationType,
        public ?string $notes,
        public ?string $externalRef,
    ) {}

    /** @param array<string,mixed> $data */
    public static function fromResponse(array $data): self
    {
        $indices = is_array($data['indices'] ?? null) ? $data['indices'] : [];
        $severity = is_array($data['burn_severity'] ?? null) ? $data['burn_severity'] : [];
        $area = is_array($data['burned_area'] ?? null) ? $data['burned_area'] : [];

        return new self(
            nbrPre: self::floatOrNull($indices['nbr_pre'] ?? null),
            nbrPost: self::floatOrNull($indices['nbr_post'] ?? null),
            dnbr: self::floatOrNull($indices['dnbr'] ?? null),
            rbr: self::floatOrNull($indices['rbr'] ?? null),
            ndviPre: self::floatOrNull($indices['ndvi_pre'] ?? null),
            ndviPost: self::floatOrNull($indices['ndvi_post'] ?? null),
            bai: self::floatOrNull($indices['bai'] ?? null),
            severityClass: self::stringOrNull($severity['class'] ?? null),
            dnbrRange: self::stringOrNull($severity['dnbr_range'] ?? null),
            confidence: self::stringOrNull($severity['confidence'] ?? null),
            areaHa: self::floatOrNull($area['area_ha'] ?? null),
            perimeterKm: self::floatOrNull($area['perimeter_km'] ?? null),
            geometryGeojson: is_array($area['geometry_geojson'] ?? null) ? $area['geometry_geojson'] : null,
            vegetationType: self::stringOrNull($data['vegetation_type'] ?? null),
            notes: self::stringOrNull($data['notes'] ?? null),
            externalRef: self::stringOrNull($data['analysis_id'] ?? ($data['external_ref'] ?? null)),
        );
    }

    /** @return array<string,mixed> Atributos prontos para persistir em FireScarAnalysis. */
    public function toModelAttributes(): array
    {
        return [
            'nbr_pre' => $this->nbrPre,
            'nbr_post' => $this->nbrPost,
            'dnbr' => $this->dnbr,
            'rbr' => $this->rbr,
            'ndvi_pre' => $this->ndviPre,
            'ndvi_post' => $this->ndviPost,
            'bai' => $this->bai,
            'severity_class' => $this->severityClass,
            'dnbr_range' => $this->dnbrRange,
            'confidence' => $this->confidence,
            'area_ha' => $this->areaHa,
            'perimeter_km' => $this->perimeterKm,
            'geometry_geojson' => $this->geometryGeojson,
            'vegetation_type' => $this->vegetationType,
            'notes' => $this->notes,
            'external_ref' => $this->externalRef,
        ];
    }

    private static function floatOrNull(mixed $value): ?float
    {
        return is_numeric($value) ? (float) $value : null;
    }

    private static function stringOrNull(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_scalar($value) ? (string) $value : null;
    }
}
