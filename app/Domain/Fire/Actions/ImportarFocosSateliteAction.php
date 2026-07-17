<?php

declare(strict_types=1);

namespace App\Domain\Fire\Actions;

use App\Models\Foco;
use App\Models\FocoSatelite;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Throwable;

final class ImportarFocosSateliteAction
{
    public function execute(): int
    {
        return $this->importBatch((int) config('fire.import_batch_incremental', 100));
    }

    public function importAll(): int
    {
        $imported = 0;
        $batchSize = (int) config('fire.import_batch_bulk', 1000);

        while (true) {
            $batchImported = $this->importBatch($batchSize, bulk: true);

            if ($batchImported === 0) {
                break;
            }

            $imported += $batchImported;
        }

        return $imported;
    }

    private function importBatch(int $limit, bool $bulk = false): int
    {
        $registros = $this->pendingQuery()
            ->orderBy('id')
            ->limit($limit)
            ->get();

        if ($registros->isEmpty()) {
            return 0;
        }

        if ($bulk) {
            return $this->importBulk($registros);
        }

        return $this->importIncremental($registros);
    }

    /**
     * @param  Collection<int, FocoSatelite>  $registros
     */
    private function importBulk(Collection $registros): int
    {
        $ids = $registros->pluck('id')->all();
        $now = now();

        FocoSatelite::query()
            ->whereIn('id', $ids)
            ->update(['status_integracao' => 'PROCESSANDO']);

        try {
            DB::transaction(function () use ($registros, $now): void {
                Foco::query()->upsert(
                    $registros
                        ->map(fn (FocoSatelite $registro): array => $this->toFocoAttributes($registro, $now, forUpsert: true))
                        ->all(),
                    ['origem_id'],
                    [
                        'satelite',
                        'sensor',
                        'pais',
                        'estado',
                        'municipio',
                        'bioma',
                        'latitude',
                        'longitude',
                        'data_hora_gmt',
                        'data_hora_brasilia',
                        'risco_fogo',
                        'temperatura',
                        'confianca',
                        'status',
                        'recebido_em',
                        'updated_at',
                    ],
                );
            });

            FocoSatelite::query()
                ->whereIn('id', $ids)
                ->update(['status_integracao' => 'PROCESSADO']);

            return $registros->count();
        } catch (Throwable $e) {
            FocoSatelite::query()
                ->whereIn('id', $ids)
                ->update(['status_integracao' => 'ERRO']);

            report($e);

            return 0;
        }
    }

    /**
     * @param  Collection<int, FocoSatelite>  $registros
     */
    private function importIncremental(Collection $registros): int
    {
        $imported = 0;

        foreach ($registros as $registro) {
            try {
                $registro->update([
                    'status_integracao' => 'PROCESSANDO',
                ]);

                DB::transaction(function () use ($registro): void {
                    Foco::query()->updateOrCreate(
                        ['origem_id' => $registro->id],
                        $this->toFocoAttributes($registro),
                    );
                });

                $registro->update([
                    'status_integracao' => 'PROCESSADO',
                ]);

                $imported++;
            } catch (Throwable $e) {
                $registro->update([
                    'status_integracao' => 'ERRO',
                ]);

                report($e);
            }
        }

        return $imported;
    }

    /**
     * @return Builder<FocoSatelite>
     */
    private function pendingQuery(): Builder
    {
        return FocoSatelite::query()
            ->where(function ($query): void {
                $query
                    ->whereNull('status_integracao')
                    ->orWhereIn('status_integracao', ['PENDENTE', 'PROCESSANDO', 'ERRO']);
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function toFocoAttributes(FocoSatelite $registro, ?DateTimeInterface $now = null, bool $forUpsert = false): array
    {
        $attributes = [
            'origem_id' => $registro->id,
            'satelite' => $registro->satelite_id,
            'sensor' => $registro->sensor,
            'pais' => $registro->pais,
            'estado' => $registro->estado,
            'municipio' => $registro->municipio,
            'bioma' => $registro->bioma,
            'latitude' => (float) $registro->latitude,
            'longitude' => (float) $registro->longitude,
            'data_hora_gmt' => $this->formatDateTime($registro->data_hora_gmt),
            'data_hora_brasilia' => $this->formatDateTime($registro->data_hora_local),
            'risco_fogo' => $registro->risco_fogo_inpe !== null ? (float) $registro->risco_fogo_inpe : null,
            'temperatura' => $registro->temperatura_brilho !== null ? (float) $registro->temperatura_brilho : null,
            'confianca' => $registro->confianca,
            'status' => 'PROCESSADO',
            'recebido_em' => $this->formatDateTime($registro->data_insercao),
        ];

        if ($forUpsert) {
            $timestamp = ($now ?? now())->format('Y-m-d H:i:s');
            $attributes['created_at'] = $timestamp;
            $attributes['updated_at'] = $timestamp;
        }

        return $attributes;
    }

    private function formatDateTime(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }

        return (string) $value;
    }
}
