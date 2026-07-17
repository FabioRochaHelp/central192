<?php

declare(strict_types=1);

namespace App\Support\Operations;

/** Normalização do telefone da chamada para persistência (apenas dígitos, tamanho limitado). */
final class IncidentPhoneNormalizer
{
    public static function normalize(string $raw): string
    {
        $digits = preg_replace('/\D+/', '', $raw) ?? '';

        return mb_substr($digits, 0, 64);
    }

    public static function passesMinimumLength(string $normalized): bool
    {
        return mb_strlen($normalized) >= 8;
    }
}
