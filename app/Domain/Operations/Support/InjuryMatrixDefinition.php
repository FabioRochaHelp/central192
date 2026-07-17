<?php

declare(strict_types=1);

namespace App\Domain\Operations\Support;

use App\Models\InjurySite;
use Illuminate\Support\Str;

/** Regiões e tipos de lesão da matriz corporal (vitima / formulário clínico). */
final class InjuryMatrixDefinition
{
    /** @var list<string> */
    public const BODY_REGIONS = [
        'Crânio',
        'Face',
        'Pescoço',
        'Tórax',
        'Dorso',
        'Abdome',
        'Membro superior direito',
        'Membro superior esquerdo',
        'Membro inferior direito',
        'Membro inferior esquerdo',
    ];

    /** @var list<string> */
    public const LESION_TYPES = [
        'Contusão',
        'Corte',
        'Escoriação',
        'Perfurante',
        'Corte contuso',
        'Laceração/esmagamento',
        'Amputação/avulsão',
        'Fratura aberta',
        'Fratura fechada',
    ];

    public static function normalizeToken(string $value): string
    {
        return Str::of($value)
            ->ascii()
            ->lower()
            ->replaceMatches('/[^a-z0-9]+/', ' ')
            ->squish()
            ->toString();
    }

    /**
     * Primeira região corporal encontrada no texto (mais longa primeiro, para evitar “Membro superior” vs “Membro superior direito”).
     */
    public static function dominantRegionInName(string $name): ?string
    {
        $normFull = self::normalizeToken($name);
        if ($normFull === '') {
            return null;
        }

        $regions = self::BODY_REGIONS;
        usort($regions, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($regions as $region) {
            $nr = self::normalizeToken($region);
            if ($nr !== '' && str_contains($normFull, $nr)) {
                return $region;
            }
        }

        return null;
    }

    /**
     * @return array{matrix_region: ?string, matrix_lesion: ?string}
     */
    public static function inferMatrixFromLooseName(string $name): array
    {
        $normFull = self::normalizeToken($name);
        if ($normFull === '') {
            return ['matrix_region' => null, 'matrix_lesion' => null];
        }

        $regionsSorted = self::BODY_REGIONS;
        usort($regionsSorted, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

        foreach ($regionsSorted as $region) {
            $regionNorm = self::normalizeToken($region);
            if ($regionNorm === '' || ! str_contains($normFull, $regionNorm)) {
                continue;
            }

            $lesionsSorted = self::LESION_TYPES;
            usort($lesionsSorted, fn (string $a, string $b): int => strlen($b) <=> strlen($a));

            foreach ($lesionsSorted as $lesion) {
                $lesionNorm = self::normalizeToken($lesion);
                if ($lesionNorm !== '' && str_contains($normFull, $lesionNorm)) {
                    return ['matrix_region' => $region, 'matrix_lesion' => $lesion];
                }
            }
        }

        return ['matrix_region' => null, 'matrix_lesion' => null];
    }

    /**
     * @return array{matrix_region: ?string, matrix_lesion: ?string}
     */
    public static function inferMatrixFromName(string $name): array
    {
        $trimmed = trim($name);
        if ($trimmed === '') {
            return ['matrix_region' => null, 'matrix_lesion' => null];
        }

        if (preg_match('/^(.+?)\s*\p{Pd}\s*(.+)$/u', $trimmed, $m) || preg_match('/^(.+?)\s*:\s*(.+)$/u', $trimmed, $m)) {
            $region = trim($m[1]);
            $lesion = trim($m[2]);
            if (in_array($region, self::BODY_REGIONS, true) && in_array($lesion, self::LESION_TYPES, true)) {
                return ['matrix_region' => $region, 'matrix_lesion' => $lesion];
            }
        }

        return self::inferMatrixFromLooseName($trimmed);
    }

    /**
     * Posição na matriz para agrupar um cadastro de parâmetros (colunas ou inferência pelo nome).
     *
     * @return array{0: string, 1: string}|null
     */
    public static function resolvePlacementForCatalog(InjurySite $site): ?array
    {
        $dbR = $site->matrix_region;
        $dbL = $site->matrix_lesion;
        if ($dbR !== null && $dbL !== null
            && in_array($dbR, self::BODY_REGIONS, true)
            && in_array($dbL, self::LESION_TYPES, true)) {
            return [$dbR, $dbL];
        }

        $inf = self::inferMatrixFromName((string) $site->name);
        if ($inf['matrix_region'] !== null && $inf['matrix_lesion'] !== null) {
            return [$inf['matrix_region'], $inf['matrix_lesion']];
        }

        return null;
    }
}
