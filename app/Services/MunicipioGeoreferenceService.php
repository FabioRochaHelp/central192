<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\IbgeMunicipio;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

final class MunicipioGeoreferenceService
{
    /**
     * Localiza o registro IBGE correspondente a uma cidade/UF.
     * A comparação ignora caixa alta/baixa, acentos e espaços extras.
     */
    public function find(string $city, string $state): ?IbgeMunicipio
    {
        $city = trim($city);
        $state = strtoupper(trim($state));

        if ($city === '' || $state === '') {
            return null;
        }

        $normalizedCity = $this->normalize($city);

        $ibge = IbgeMunicipio::query()
            ->where('uf', $state)
            ->get()
            ->first(fn (IbgeMunicipio $m) => $this->normalize($m->nome) === $normalizedCity);

        if ($ibge !== null) {
            Log::info('IbgeMunicipio localizado', [
                'city' => $city,
                'state' => $state,
                'ibge_id' => $ibge->id,
                'codigo_ibge' => $ibge->codigo_ibge,
            ]);
        } else {
            Log::warning('IbgeMunicipio não localizado', [
                'city' => $city,
                'state' => $state,
            ]);
        }

        return $ibge;
    }

    /**
     * Normaliza string para comparação: minúsculas, sem acentos, sem espaços extras.
     */
    public function normalize(string $value): string
    {
        return strtolower(trim(Str::ascii($value)));
    }
}
