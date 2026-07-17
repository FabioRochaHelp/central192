<?php

declare(strict_types=1);

namespace App\Http\Controllers\Operations;

use App\Http\Controllers\Controller;
use App\Models\Incident;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reproduz a gravação da chamada armazenada no PABX (Asterisk).
 *
 * O áudio é buscado no PABX pelo servidor (Bearer token nunca vai ao navegador) e
 * repassado em streaming. Acesso restrito ao administrador central; suporta `Range`
 * para permitir busca (seek) no player.
 */
final class PlayIncidentRecordingController extends Controller
{
    public function __invoke(Request $request, Incident $incident): StreamedResponse
    {
        abort_unless($request->user()?->isCentralAdministrator() === true, 403);

        $uniqueid = trim((string) $incident->pabx_uniqueid);
        abort_if($uniqueid === '', 404, __('Esta ocorrência não possui gravação vinculada.'));

        $token = (string) config('operations.pabx_recording_token');
        abort_if($token === '', 503, __('Integração de gravação do PABX não configurada (OPERATIONS_PABX_TOKEN).'));

        $url = config('operations.pabx_recording_base_url').'/recordings/'.rawurlencode($uniqueid).'/play';

        $range = (string) $request->header('Range', '');

        try {
            $upstream = Http::withToken($token)
                ->withHeaders(array_filter(['Range' => $range !== '' ? $range : null]))
                ->withOptions(['stream' => true])
                ->timeout(30)
                ->get($url);
        } catch (ConnectionException $e) {
            report($e);
            abort(502, __('Não foi possível conectar ao PABX para obter a gravação.'));
        }

        abort_unless($upstream->successful(), $upstream->status() === 404 ? 404 : 502, __('Não foi possível obter a gravação no PABX.'));

        $body = $upstream->toPsrResponse()->getBody();

        $headers = array_filter([
            'Content-Type' => $upstream->header('Content-Type') ?: 'audio/wav',
            'Accept-Ranges' => 'bytes',
            'Content-Length' => $upstream->header('Content-Length') ?: null,
            'Content-Range' => $upstream->header('Content-Range') ?: null,
            'Cache-Control' => 'private, no-store',
        ]);

        return response()->stream(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(8192);
                flush();
            }
        }, $upstream->status(), $headers);
    }
}
