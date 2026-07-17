<?php

declare(strict_types=1);

namespace App\Domain\Operations\Enums;

/**
 * Conduta registrada pelo médico regulador ao fim da regulação.
 *
 * @see docs/regulacao/plano-implementacao.md
 */
enum RegulationDecision: string
{
    /** Autoriza envio de recurso — ocorrência vai para a fila de despacho (status OPEN). */
    case DispatchResource = 'dispatch_resource';
    /** Orientação médica ao solicitante — encerra sem viatura. */
    case MedicalGuidance = 'medical_guidance';
    /** Transferência para outro serviço (UPA, hospital, etc.) — encerra sem viatura. */
    case Transfer = 'transfer';
    /** Recusa — não caracteriza urgência/emergência — encerra sem viatura. */
    case Refused = 'refused';

    public function label(): string
    {
        return match ($this) {
            self::DispatchResource => __('Enviar recurso'),
            self::MedicalGuidance => __('Orientação médica'),
            self::Transfer => __('Transferência'),
            self::Refused => __('Recusa'),
        };
    }

    /** Decisão que libera a ocorrência para empenho de viatura. */
    public function authorizesDispatch(): bool
    {
        return $this === self::DispatchResource;
    }
}
