<?php

declare(strict_types=1);

namespace App\Domain\Fire\Enums;

/**
 * Classes de severidade de queima (convenção USGS/FIREMON sobre dNBR).
 *
 * Os limiares são de ordem de grandeza (calibrados para floresta temperada) e
 * devem ser recalibrados para biomas brasileiros — ver
 * `.agents/skills/wildfire-scar-analysis/references/burn_severity_classification.md`.
 */
enum FireScarSeverity: string
{
    case RebrotaAlta = 'rebrota_alta';
    case RebrotaBaixa = 'rebrota_baixa';
    case NaoQueimado = 'nao_queimado';
    case Baixa = 'baixa';
    case ModeradaBaixa = 'moderada_baixa';
    case ModeradaAlta = 'moderada_alta';
    case Alta = 'alta';

    public function label(): string
    {
        return match ($this) {
            self::RebrotaAlta => 'Rebrota alta',
            self::RebrotaBaixa => 'Rebrota baixa',
            self::NaoQueimado => 'Não queimado',
            self::Baixa => 'Severidade baixa',
            self::ModeradaBaixa => 'Severidade moderada-baixa',
            self::ModeradaAlta => 'Severidade moderada-alta',
            self::Alta => 'Severidade alta',
        };
    }

    /** Cor hex para pintar o polígono da cicatriz no mapa. */
    public function color(): string
    {
        return match ($this) {
            self::RebrotaAlta => '#1a9850',
            self::RebrotaBaixa => '#91cf60',
            self::NaoQueimado => '#d9ef8b',
            self::Baixa => '#fee08b',
            self::ModeradaBaixa => '#fc8d59',
            self::ModeradaAlta => '#e34a33',
            self::Alta => '#b30000',
        };
    }

    /** Considera queimado tudo com severidade >= baixa. */
    public function isBurned(): bool
    {
        return match ($this) {
            self::Baixa, self::ModeradaBaixa, self::ModeradaAlta, self::Alta => true,
            default => false,
        };
    }

    /**
     * Classifica pela convenção USGS (dNBR não escalado).
     * Fallback quando o serviço externo não devolve a classe já rotulada.
     */
    public static function fromDnbr(float $dnbr): self
    {
        return match (true) {
            $dnbr < -0.251 => self::RebrotaAlta,
            $dnbr < -0.101 => self::RebrotaBaixa,
            $dnbr < 0.100 => self::NaoQueimado,
            $dnbr < 0.270 => self::Baixa,
            $dnbr < 0.440 => self::ModeradaBaixa,
            $dnbr < 0.660 => self::ModeradaAlta,
            default => self::Alta,
        };
    }

    /** Resolve a partir de um valor livre vindo do serviço externo (rótulo ou value). */
    public static function tryFromLabel(?string $value): ?self
    {
        if ($value === null || $value === '') {
            return null;
        }

        $normalized = str_replace([' ', '-'], '_', mb_strtolower(trim($value)));

        foreach (self::cases() as $case) {
            if ($case->value === $normalized || str_replace([' ', '-'], '_', mb_strtolower($case->label())) === $normalized) {
                return $case;
            }
        }

        return null;
    }
}
