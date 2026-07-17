<?php

namespace App\Console\Commands;

use App\Models\FocoSatelite;
use App\Models\Foco;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportarFocosSatelite extends Command
{
    protected $signature = 'focos:importar';

    protected $description = 'Importa focos do banco satélite';

    public function handle()
    {
        FocoSatelite::where('status_integracao', 'PENDENTE')
            ->orderBy('id')
            ->limit(100)
            ->get()
            ->each(function ($registro) {

                try {

                    $registro->update([
                        'status_integracao' => 'PROCESSANDO'
                    ]);

                    DB::transaction(function () use ($registro) {

                        Foco::create([
                            'origem_id' => $registro->id,
                            'latitude' => $registro->latitude,
                            'longitude' => $registro->longitude,
                            'municipio' => $registro->municipio,
                            'estado' => $registro->estado,
                            'bioma' => $registro->bioma,
                            'satelite' => $registro->satelite,
                            'sensor' => $registro->sensor,
                            'data_foco' => $registro->data_hora_brasilia,
                        ]);

                    });

                    $registro->update([
                        'status_integracao' => 'PROCESSADO'
                    ]);

                } catch (\Throwable $e) {

                    $registro->update([
                        'status_integracao' => 'ERRO'
                    ]);

                    report($e);
                }
            });

        return self::SUCCESS;
    }
}
