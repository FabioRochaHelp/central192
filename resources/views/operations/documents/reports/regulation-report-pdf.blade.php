<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Relatório de regulação médica') }}</title>
    <style>
        @page { margin: 28px 32px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #18181b; line-height: 1.45; margin: 0; }
        h1 { font-size: 18px; margin: 0 0 2px; }
        .muted { color: #6b7280; }
        .section { margin-top: 16px; }
        .section-title { font-size: 13px; font-weight: bold; border-bottom: 1px solid #e5e7eb; padding-bottom: 4px; margin-bottom: 6px; }
        table { width: 100%; border-collapse: collapse; }
        td, th { padding: 4px 6px; text-align: left; }
        th { background: #f4f4f5; }
        .num { text-align: right; }
        .kpi { display: inline-block; width: 24%; }
        .kpi .big { font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ __('Relatório de regulação médica') }}</h1>
    <div class="muted">{{ __('Período') }}: {{ \Illuminate\Support\Carbon::parse($data['period']['from'])->format('d/m/Y') }} — {{ \Illuminate\Support\Carbon::parse($data['period']['to'])->format('d/m/Y') }} · {{ __('Gerado em') }} {{ now()->format('d/m/Y H:i') }}</div>

    <div class="section">
        <div class="kpi"><div class="muted">{{ __('Regulações') }}</div><div class="big">{{ number_format($data['total'], 0, ',', '.') }}</div></div>
        <div class="kpi"><div class="muted">{{ __('Tempo médio') }}</div><div class="big">{{ $data['response_time']['avg_response_min'] !== null ? number_format($data['response_time']['avg_response_min'], 1, ',', '.').' min' : '—' }}</div></div>
        <div class="kpi"><div class="muted">{{ __('% envio') }}</div><div class="big">{{ $data['dispatch_pct'] !== null ? number_format($data['dispatch_pct'], 1, ',', '.').'%' : '—' }}</div></div>
        <div class="kpi"><div class="muted">{{ __('% orientação') }}</div><div class="big">{{ $data['guidance_pct'] !== null ? number_format($data['guidance_pct'], 1, ',', '.').'%' : '—' }}</div></div>
    </div>

    <div class="section">
        <div class="section-title">{{ __('Por decisão') }}</div>
        <table>
            <thead><tr><th>{{ __('Decisão') }}</th><th class="num">{{ __('Qtde') }}</th></tr></thead>
            <tbody>
                @foreach ($data['by_decision'] as $row)
                    <tr><td>{{ $row['label'] }}</td><td class="num">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('Por prioridade') }}</div>
        <table>
            <thead><tr><th>{{ __('Prioridade') }}</th><th class="num">{{ __('Qtde') }}</th></tr></thead>
            <tbody>
                @foreach ($data['by_priority'] as $row)
                    <tr><td>{{ $row['label'] }}</td><td class="num">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('Por recurso indicado') }}</div>
        <table>
            <thead><tr><th>{{ __('Recurso') }}</th><th class="num">{{ __('Qtde') }}</th></tr></thead>
            <tbody>
                @foreach ($data['by_resource'] as $row)
                    <tr><td>{{ $row['label'] }}</td><td class="num">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('Por médico regulador') }}</div>
        <table>
            <thead><tr><th>{{ __('Médico') }}</th><th class="num">{{ __('Regulações') }}</th><th class="num">{{ __('Tempo médio (min)') }}</th></tr></thead>
            <tbody>
                @foreach ($data['by_regulator'] as $row)
                    <tr>
                        <td>{{ $row['name'] }}</td>
                        <td class="num">{{ number_format($row['count'], 0, ',', '.') }}</td>
                        <td class="num">{{ $row['avg_response_min'] !== null ? number_format($row['avg_response_min'], 1, ',', '.') : '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
