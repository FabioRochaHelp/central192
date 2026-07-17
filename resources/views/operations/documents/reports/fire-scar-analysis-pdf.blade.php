<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Cicatriz de incêndio') }} #{{ $analysis->id }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #18181b; line-height: 1.45; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #6b7280; }
        .section { margin-top: 18px; }
        .section-title { font-size: 13px; font-weight: bold; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 8px; }
        table { width: 100%; border-collapse: collapse; }
        td { padding: 4px 6px; vertical-align: top; }
        .kv td:first-child { color: #6b7280; width: 40%; }
        .badge { display: inline-block; padding: 2px 8px; border-radius: 10px; color: #fff; font-size: 11px; }
        .grid td { width: 33%; }
        .big { font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ __('Relatório de cicatriz de incêndio') }}</h1>
    <div class="muted">
        {{ $analysis->municipio ?? '—' }}{{ $analysis->estado ? '/'.$analysis->estado : '' }}
        · {{ number_format((float) $analysis->latitude, 5) }}, {{ number_format((float) $analysis->longitude, 5) }}
        · {{ __('Gerado em') }} {{ now()->format('d/m/Y H:i') }}
    </div>

    <div class="section">
        <div class="section-title">{{ __('Resumo') }}</div>
        <table class="grid">
            <tr>
                <td>
                    <div class="muted">{{ __('Área queimada') }}</div>
                    <div class="big">{{ $analysis->area_ha !== null ? number_format((float) $analysis->area_ha, 2, ',', '.').' ha' : '—' }}</div>
                </td>
                <td>
                    <div class="muted">{{ __('Perímetro') }}</div>
                    <div class="big">{{ $analysis->perimeter_km !== null ? number_format((float) $analysis->perimeter_km, 2, ',', '.').' km' : '—' }}</div>
                </td>
                <td>
                    <div class="muted">{{ __('Severidade') }}</div>
                    <div class="big">
                        @if ($severity)
                            <span class="badge" style="background: {{ $severity->color() }}">{{ $severity->label() }}</span>
                        @else — @endif
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('Índices espectrais') }}</div>
        <table class="kv">
            @foreach ([
                'NBR pré' => $analysis->nbr_pre, 'NBR pós' => $analysis->nbr_post, 'dNBR' => $analysis->dnbr,
                'RBR' => $analysis->rbr, 'NDVI pré' => $analysis->ndvi_pre, 'NDVI pós' => $analysis->ndvi_post, 'BAI' => $analysis->bai,
            ] as $label => $value)
                <tr><td>{{ $label }}</td><td>{{ $value !== null ? number_format((float) $value, 4, ',', '.') : '—' }}</td></tr>
            @endforeach
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('Detalhes') }}</div>
        <table class="kv">
            <tr><td>{{ __('Sensor') }}</td><td>{{ $analysis->sensor ?? '—' }}</td></tr>
            <tr><td>{{ __('Confiança') }}</td><td>{{ $analysis->confidence ?? '—' }}</td></tr>
            <tr><td>{{ __('dNBR (faixa)') }}</td><td>{{ $analysis->dnbr_range ?? '—' }}</td></tr>
            <tr><td>{{ __('Bioma') }}</td><td>{{ $analysis->bioma ?? '—' }}</td></tr>
            <tr><td>{{ __('Tipo de vegetação') }}</td><td>{{ $analysis->vegetation_type ?? '—' }}</td></tr>
            <tr><td>{{ __('Data pré-fogo') }}</td><td>{{ $analysis->pre_fire_date?->format('d/m/Y') ?? '—' }}</td></tr>
            <tr><td>{{ __('Data pós-fogo') }}</td><td>{{ $analysis->post_fire_date?->format('d/m/Y') ?? '—' }}</td></tr>
            @if ($analysis->incident)
                <tr><td>{{ __('Ocorrência vinculada') }}</td><td>{{ $analysis->incident->talao ?? __('s/ talão') }}/{{ $analysis->incident->dispatch_year }}</td></tr>
            @endif
        </table>
    </div>

    @if ($analysis->notes)
        <div class="section">
            <div class="section-title">{{ __('Notas') }}</div>
            <div>{{ $analysis->notes }}</div>
        </div>
    @endif

    <div class="section muted" style="font-size: 10px;">
        {{ __('Limiares de severidade seguem a convenção USGS/FIREMON e servem como referência de ordem de grandeza; recomenda-se validação de campo e recalibração por bioma.') }}
    </div>
</body>
</html>
