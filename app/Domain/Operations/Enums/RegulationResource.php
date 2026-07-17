<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

/**
 * Grade de resposta indicada pelo médico regulador (recurso a ser empenhado).
 *
 * @see docs/regulacao/plano-implementacao.md
 */
enum RegulationResource: string
{
    /** Unidade de Suporte Básico. */
    case Usb = 'usb';
    /** Unidade de Suporte Avançado (com médico). */
    case Usa = 'usa';
    /** Veículo de Intervenção Rápida. */
    case Vir = 'vir';
    /** Motolância. */
    case Moto = 'moto';
    /** Aeromédico. */
    case Aero = 'aero';
    /** Nenhum recurso (não deve acompanhar decisão de envio). */
    case None = 'none';

    public function label(): string
    {
        return match ($this) {
            self::Usb => __('USB — Suporte Básico'),
            self::Usa => __('USA — Suporte Avançado'),
            self::Vir => __('VIR — Intervenção Rápida'),
            self::Moto => __('Motolância'),
            self::Aero => __('Aeromédico'),
            self::None => __('Sem recurso'),
        };
    }

    /** Recursos ofertáveis numa decisão de envio (exclui None). */
    public static function dispatchable(): array
    {
        return [self::Usb, self::Usa, self::Vir, self::Moto, self::Aero];
    }
}
