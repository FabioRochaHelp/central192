<?php

declare(strict_types=1);

namespace App\Support\Operations;

/**
 * Contexto operacional da partição administrativa (docs: municipio_id, não SaaS isolado).
 */
final class CurrentMunicipio
{
    private static ?int $id = null;

    public static function set(?int $id): void
    {
        self::$id = $id;
    }

    public static function id(): ?int
    {
        return self::$id;
    }

    public static function resolved(): bool
    {
        return self::$id !== null;
    }
}
