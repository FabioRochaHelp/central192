<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Ficha de regulação médica') }}</title>
    <style>
        @page { margin: 30px 34px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #18181b; line-height: 1.5; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #6b7280; }
        .section { margin-top: 14px; }
        .section-title { font-size: 13px; font-weight: bold; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; vertical-align: top; text-align: left; }
        .label { color: #6b7280; width: 32%; }
        .box { border: 1px solid #e5e7eb; border-radius: 4px; padding: 8px 10px; white-space: pre-line; }
        .sign { margin-top: 40px; }
        .sign-line { border-top: 1px solid #18181b; width: 60%; padding-top: 4px; }
    </style>
</head>
<body>
    <h1>{{ __('Ficha de regulação médica') }}</h1>
    <div class="muted">
        {{ __('Talão') }} {{ $incident->talao }}/{{ $incident->dispatch_year }}
        · {{ $incident->municipio?->city ?? '—' }}
        · {{ __('Gerado em') }} {{ now()->format('d/m/Y H:i') }}
    </div>

    <div class="section">
        <div class="section-title">{{ __('Ocorrência') }}</div>
        <table>
            <tr><td class="label">{{ __('Natureza') }}</td><td>{{ $incident->nature?->name ?? '—' }}</td></tr>
            <tr><td class="label">{{ __('Paciente') }}</td><td>
                {{ $incident->patient_name ?: __('Não identificado') }}
                @if ($incident->patient_age)· {{ $incident->patient_age }} {{ __('anos') }}@endif
                @if ($incident->patient_sex)· {{ $incident->patient_sex }}@endif
            </td></tr>
            <tr><td class="label">{{ __('Local') }}</td><td>{{ $incident->address_line }}{{ $incident->number ? ', '.$incident->number : '' }}{{ $incident->district ? ' — '.$incident->district : '' }}{{ $incident->city ? ' / '.$incident->city : '' }}</td></tr>
            <tr><td class="label">{{ __('Ligação recebida') }}</td><td>{{ optional($incident->call_received_at)->format('d/m/Y H:i') ?? '—' }}</td></tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('Regulação') }}</div>
        <table>
            <tr><td class="label">{{ __('Médico regulador') }}</td><td>{{ $regulation->regulator?->name ?? '—' }}</td></tr>
            <tr><td class="label">{{ __('Decisão') }}</td><td>{{ $regulation->status?->label() ?? '—' }}</td></tr>
            <tr><td class="label">{{ __('Prioridade') }}</td><td>{{ $regulation->priority?->label() ?? '—' }}</td></tr>
            @if ($regulation->recommended_resource)
                <tr><td class="label">{{ __('Recurso indicado') }}</td><td>{{ $regulation->recommended_resource->label() }}</td></tr>
            @endif
            <tr><td class="label">{{ __('Assumida em') }}</td><td>{{ optional($regulation->assumed_at)->format('d/m/Y H:i:s') ?? '—' }}</td></tr>
            <tr><td class="label">{{ __('Regulada em') }}</td><td>{{ optional($regulation->decided_at)->format('d/m/Y H:i:s') ?? '—' }}</td></tr>
            @if ($regulation->response_time_seconds !== null)
                <tr><td class="label">{{ __('Tempo-resposta') }}</td><td>{{ gmdate('H:i:s', $regulation->response_time_seconds) }}</td></tr>
            @endif
        </table>
    </div>

    @if ($regulation->diagnostic_hypothesis)
        <div class="section">
            <div class="section-title">{{ __('Hipótese diagnóstica') }}</div>
            <div class="box">{{ $regulation->diagnostic_hypothesis }}</div>
        </div>
    @endif

    @if ($regulation->guidance_notes)
        <div class="section">
            <div class="section-title">{{ __('Orientações') }}</div>
            <div class="box">{{ $regulation->guidance_notes }}</div>
        </div>
    @endif

    @if ($regulation->transfer_target)
        <div class="section">
            <div class="section-title">{{ __('Destino da transferência') }}</div>
            <div class="box">{{ $regulation->transfer_target }}</div>
        </div>
    @endif

    @if ($regulation->refusal_reason)
        <div class="section">
            <div class="section-title">{{ __('Motivo da recusa') }}</div>
            <div class="box">{{ $regulation->refusal_reason }}</div>
        </div>
    @endif

    <div class="sign">
        <div class="sign-line">{{ $regulation->regulator?->name ?? __('Médico regulador') }}</div>
    </div>
</body>
</html>
