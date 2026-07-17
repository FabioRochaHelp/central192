<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Municipio;
use App\Services\MunicipioGeoreferenceService;
use Illuminate\Console\Command;

/**
 * Backfill: vincula cada Municipio sem ibge_municipio_id ao registro IBGE correspondente
 * usando normalização de nome (ignora acentos e caixa) via MunicipioGeoreferenceService.
 */
class VincularIbgeMunicipios extends Command
{
    protected $signature = 'municipios:vincular-ibge';

    protected $description = 'Vincula municípios sem ibge_municipio_id ao registro IBGE correspondente';

    public function handle(MunicipioGeoreferenceService $geo): int
    {
        $pendentes = Municipio::query()
            ->whereNull('ibge_municipio_id')
            ->whereNotNull('city')
            ->whereNotNull('state')
            ->where('city', '!=', '')
            ->where('state', '!=', '')
            ->get();

        $total      = $pendentes->count();
        $vinculados = 0;

        foreach ($pendentes as $municipio) {
            $ibge = $geo->find((string) $municipio->city, (string) $municipio->state);

            if ($ibge !== null) {
                $municipio->update(['ibge_municipio_id' => $ibge->id]);
                $vinculados++;
            }
        }

        $semCorrespondencia = $total - $vinculados;

        $this->line("Vinculados: {$vinculados} | Sem correspondência: {$semCorrespondencia} | Total: {$total}");

        return self::SUCCESS;
    }
}
