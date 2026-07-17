<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Importa municípios IBGE a partir de Shapefile (.shp + .dbf).
 *
 * Fonte: Malha Municipal Digital (MMD) – IBGE 2025
 * Arquivo: storage/app/ibge/SP_Municipios_2025.shp
 *
 * Mapeamento de colunas DBF → ibge_municipios:
 *   CD_MUN   → codigo_ibge
 *   NM_MUN   → nome
 *   SIGLA_UF → uf
 *   CD_UF    → codigo_uf
 *   AREA_KM2 → area_km2
 *
 * Geometria: lida do .shp, convertida para WKT e importada via
 * ST_ForcePolygonCCW(ST_GeomFromText(wkt, 4674)) — SRID SIRGAS 2000.
 */
class ImportarIbgeMunicipios extends Command
{
    protected $signature = 'ibge:importar-municipios
                            {--path= : Caminho base do shapefile (sem extensão)}
                            {--sem-geometria : Importa apenas dados tabulares, sem geometria}';

    protected $description = 'Importa municípios IBGE a partir de Shapefile (.shp / .dbf)';

    public function handle(): int
    {
        $basePath = $this->option('path')
            ?? storage_path('app/ibge/SP_Municipios_2025');

        $dbfPath = $basePath . '.dbf';
        $shpPath = $basePath . '.shp';

        if (! file_exists($dbfPath)) {
            $this->error("Arquivo DBF não encontrado: {$dbfPath}");
            return self::FAILURE;
        }

        $comGeometria = ! $this->option('sem-geometria');

        if ($comGeometria && ! file_exists($shpPath)) {
            $this->warn("Arquivo SHP não encontrado: {$shpPath}. Importando apenas dados tabulares.");
            $comGeometria = false;
        }

        $this->info('Lendo atributos do DBF…');
        $records = $this->readDbf($dbfPath);

        $this->info(sprintf('Encontrados %d registros.', count($records)));

        $geometries = [];
        if ($comGeometria) {
            $this->info('Lendo geometrias do SHP…');
            $geometries = $this->readShpWkt($shpPath, count($records));
        }

        $this->info('Importando para banco de dados…');

        $bar = $this->output->createProgressBar(count($records));
        $bar->start();

        $inserted = 0;
        $updated  = 0;

        DB::beginTransaction();
        try {
            foreach ($records as $i => $rec) {
                $existing = DB::table('ibge_municipios')
                    ->where('codigo_ibge', $rec['codigo_ibge'])
                    ->first();

                $payload = [
                    'codigo_ibge' => $rec['codigo_ibge'],
                    'nome'        => $rec['nome'],
                    'uf'          => $rec['uf'],
                    'codigo_uf'   => $rec['codigo_uf'],
                    'area_km2'    => $rec['area_km2'],
                    'updated_at'  => now(),
                ];

                $wkt = $geometries[$i] ?? null;

                if ($existing === null) {
                    $payload['created_at'] = now();

                    if ($wkt !== null) {
                        DB::statement(
                            "INSERT INTO ibge_municipios
                             (codigo_ibge, nome, uf, codigo_uf, area_km2, geometry, latitude, longitude, created_at, updated_at)
                             VALUES (?, ?, ?, ?, ?,
                                     ST_ForcePolygonCCW(ST_GeomFromText(?, 4674)),
                                     ST_Y(ST_Centroid(ST_GeomFromText(?, 4674))),
                                     ST_X(ST_Centroid(ST_GeomFromText(?, 4674))),
                                     ?, ?)",
                            [
                                $rec['codigo_ibge'], $rec['nome'], $rec['uf'],
                                $rec['codigo_uf'], $rec['area_km2'],
                                $wkt, $wkt, $wkt,
                                now(), now(),
                            ]
                        );
                    } else {
                        DB::table('ibge_municipios')->insert($payload);
                    }

                    $inserted++;
                } else {
                    if ($wkt !== null) {
                        DB::statement(
                            "UPDATE ibge_municipios
                             SET nome=?, uf=?, codigo_uf=?, area_km2=?,
                                 geometry=ST_ForcePolygonCCW(ST_GeomFromText(?, 4674)),
                                 latitude=ST_Y(ST_Centroid(ST_GeomFromText(?, 4674))),
                                 longitude=ST_X(ST_Centroid(ST_GeomFromText(?, 4674))),
                                 updated_at=?
                             WHERE codigo_ibge=?",
                            [
                                $rec['nome'], $rec['uf'], $rec['codigo_uf'], $rec['area_km2'],
                                $wkt, $wkt, $wkt,
                                now(),
                                $rec['codigo_ibge'],
                            ]
                        );
                    } else {
                        DB::table('ibge_municipios')
                            ->where('codigo_ibge', $rec['codigo_ibge'])
                            ->update($payload);
                    }

                    $updated++;
                }

                $bar->advance();
            }

            DB::commit();
        } catch (\Throwable $e) {
            DB::rollBack();
            $bar->finish();
            $this->newLine();
            $this->error('Erro durante importação: ' . $e->getMessage());
            return self::FAILURE;
        }

        $bar->finish();
        $this->newLine();

        $this->info(sprintf(
            'Concluído: %d inseridos, %d atualizados | geometria: %s',
            $inserted,
            $updated,
            $comGeometria ? 'sim' : 'não'
        ));

        return self::SUCCESS;
    }

    // -------------------------------------------------------------------------
    // DBF reader
    // -------------------------------------------------------------------------

    /** @return list<array{codigo_ibge:string, nome:string, uf:string, codigo_uf:string, area_km2:float|null}> */
    private function readDbf(string $path): array
    {
        $f = fopen($path, 'rb');

        $header     = fread($f, 32);
        $numRecords = unpack('V', substr($header, 4, 4))[1];
        $headerSize = unpack('v', substr($header, 8, 2))[1];
        $recordSize = unpack('v', substr($header, 10, 2))[1];

        // Read field descriptors
        $fields = [];
        while (true) {
            $desc = fread($f, 32);
            if (ord($desc[0]) === 0x0D) {
                break;
            }
            $name   = rtrim(substr($desc, 0, 11), "\x00");
            $type   = $desc[11];
            $length = ord($desc[16]);
            $fields[] = ['name' => $name, 'type' => $type, 'length' => $length];
        }

        fseek($f, $headerSize);

        $records = [];
        for ($r = 0; $r < $numRecords; $r++) {
            $raw = fread($f, $recordSize);
            if ($raw === false || strlen($raw) < $recordSize) {
                break;
            }

            if (ord($raw[0]) === 0x2A) { // deleted
                continue;
            }

            $row    = [];
            $offset = 1;
            foreach ($fields as $field) {
                $row[$field['name']] = mb_convert_encoding(
                    rtrim(substr($raw, $offset, $field['length'])),
                    'UTF-8',
                    'UTF-8'
                );
                $offset += $field['length'];
            }

            $records[] = [
                'codigo_ibge' => $row['CD_MUN'] ?? '',
                'nome'        => $row['NM_MUN'] ?? '',
                'uf'          => $row['SIGLA_UF'] ?? '',
                'codigo_uf'   => $row['CD_UF'] ?? null,
                'area_km2'    => isset($row['AREA_KM2']) && $row['AREA_KM2'] !== ''
                    ? (float) str_replace(',', '.', $row['AREA_KM2'])
                    : null,
            ];
        }

        fclose($f);

        return $records;
    }

    // -------------------------------------------------------------------------
    // SHP reader → WKT strings
    // -------------------------------------------------------------------------

    /** @return list<string|null> */
    private function readShpWkt(string $path, int $expectedCount): array
    {
        $f = fopen($path, 'rb');
        fseek($f, 100); // skip file header

        $wktList = [];

        while (! feof($f) && count($wktList) < $expectedCount) {
            $recHeader = fread($f, 8);
            if (strlen($recHeader) < 8) {
                break;
            }

            $contentLen = unpack('N', substr($recHeader, 4, 4))[1] * 2;
            if ($contentLen <= 0) {
                break;
            }

            $content   = fread($f, $contentLen);
            $shapeType = unpack('V', substr($content, 0, 4))[1];

            if ($shapeType === 0) {
                $wktList[] = null;
                continue;
            }

            if ($shapeType !== 5) {
                $wktList[] = null;
                continue;
            }

            // Polygon (type 5)
            // skip bbox (32 bytes) → offset 4+32 = 36
            $numParts  = unpack('V', substr($content, 36, 4))[1];
            $numPoints = unpack('V', substr($content, 40, 4))[1];

            // Parts array
            $partsOffset = 44;
            $parts = array_values(unpack("V{$numParts}", substr($content, $partsOffset, $numParts * 4)));
            $parts[] = $numPoints; // sentinel

            // Points array
            $ptsOffset = $partsOffset + $numParts * 4;
            $points    = [];
            for ($p = 0; $p < $numPoints; $p++) {
                $x        = unpack('d', substr($content, $ptsOffset + $p * 16, 8))[1];
                $y        = unpack('d', substr($content, $ptsOffset + $p * 16 + 8, 8))[1];
                $points[] = [$x, $y];
            }

            // Build rings
            $rings = [];
            for ($i = 0; $i < $numParts; $i++) {
                $rings[] = array_slice($points, $parts[$i], $parts[$i + 1] - $parts[$i]);
            }

            $wktList[] = $this->ringsToMultipolygonWkt($rings);
        }

        fclose($f);

        return $wktList;
    }

    /**
     * Converte anéis de um registro SHP em WKT POLYGON ou MULTIPOLYGON.
     *
     * Convenção ESRI para coordenadas geográficas:
     *   área negativa (CW)  → anel externo
     *   área positiva (CCW) → buraco
     *
     * @param  list<list<array{0:float,1:float}>>  $rings
     */
    private function ringsToMultipolygonWkt(array $rings): string
    {
        $polygons       = [];
        $currentOuter   = null;
        $currentHoles   = [];

        foreach ($rings as $ring) {
            $area = $this->signedArea($ring);

            if ($area <= 0) {
                // CW → anel externo (nova face)
                if ($currentOuter !== null) {
                    $polygons[] = [$currentOuter, $currentHoles];
                }
                $currentOuter = $ring;
                $currentHoles = [];
            } else {
                // CCW → buraco
                if ($currentOuter !== null) {
                    $currentHoles[] = $ring;
                }
            }
        }

        if ($currentOuter !== null) {
            $polygons[] = [$currentOuter, $currentHoles];
        }

        if (empty($polygons)) {
            return 'GEOMETRYCOLLECTION EMPTY';
        }

        $polyWkts = array_map(
            fn (array $p) => $this->polygonWkt($p[0], $p[1]),
            $polygons
        );

        if (count($polyWkts) === 1) {
            return 'POLYGON' . $polyWkts[0];
        }

        return 'MULTIPOLYGON(' . implode(', ', $polyWkts) . ')';
    }

    /** @param list<array{0:float,1:float}> $outer @param list<list<array{0:float,1:float}>> $holes */
    private function polygonWkt(array $outer, array $holes): string
    {
        $rings = [$this->ringWkt($outer)];
        foreach ($holes as $hole) {
            $rings[] = $this->ringWkt($hole);
        }
        return '(' . implode(', ', $rings) . ')';
    }

    /** @param list<array{0:float,1:float}> $pts */
    private function ringWkt(array $pts): string
    {
        $coords = array_map(
            fn (array $p) => sprintf('%.8f %.8f', $p[0], $p[1]),
            $pts
        );
        return '(' . implode(', ', $coords) . ')';
    }

    /** @param list<array{0:float,1:float}> $ring */
    private function signedArea(array $ring): float
    {
        $n    = count($ring);
        $area = 0.0;
        for ($i = 0; $i < $n; $i++) {
            $j = ($i + 1) % $n;
            $area += $ring[$i][0] * $ring[$j][1];
            $area -= $ring[$j][0] * $ring[$i][1];
        }
        return $area / 2.0;
    }
}
