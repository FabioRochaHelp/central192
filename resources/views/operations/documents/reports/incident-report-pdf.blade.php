<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ __('Relatório de ocorrências') }}</title>
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
        .kpi { display: inline-block; width: 32%; }
        .kpi .big { font-size: 16px; font-weight: bold; }
    </style>
</head>
<body>
    <h1>{{ __('Relatório de ocorrências') }}</h1>
    <div class="muted">{{ __('Período') }}: {{ \Illuminate\Support\Carbon::parse($data['period']['from'])->format('d/m/Y') }} — {{ \Illuminate\Support\Carbon::parse($data['period']['to'])->format('d/m/Y') }} · {{ __('Gerado em') }} {{ now()->format('d/m/Y H:i') }}</div>

    <div class="section">
        <div class="kpi"><div class="muted">{{ __('Total') }}</div><div class="big">{{ number_format($data['total'], 0, ',', '.') }}</div></div>
        <div class="kpi"><div class="muted">{{ __('Empenho → local (méd.)') }}</div><div class="big">{{ $data['response_time']['avg_dispatch_to_scene_min'] !== null ? number_format($data['response_time']['avg_dispatch_to_scene_min'], 1, ',', '.').' min' : '—' }}</div></div>
        <div class="kpi"><div class="muted">{{ __('Chamada → local (méd.)') }}</div><div class="big">{{ $data['response_time']['avg_call_to_scene_min'] !== null ? number_format($data['response_time']['avg_call_to_scene_min'], 1, ',', '.').' min' : '—' }}</div></div>
    </div>

    <div class="section">
        <div class="section-title">{{ __('Por modalidade') }}</div>
        <table>
            <thead><tr><th>{{ __('Modalidade') }}</th><th class="num">{{ __('Qtde') }}</th></tr></thead>
            <tbody>
                @foreach ($data['by_modality'] as $row)
                    <tr><td>{{ $row['label'] }}</td><td class="num">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('Por status') }}</div>
        <table>
            <thead><tr><th>{{ __('Status') }}</th><th class="num">{{ __('Qtde') }}</th></tr></thead>
            <tbody>
                @foreach ($data['by_status'] as $row)
                    <tr><td>{{ $row['label'] }}</td><td class="num">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>

    <div class="section">
        <div class="section-title">{{ __('Por município') }}</div>
        <table>
            <thead><tr><th>{{ __('Município') }}</th><th class="num">{{ __('Qtde') }}</th></tr></thead>
            <tbody>
                @foreach ($data['by_municipio'] as $row)
                    <tr><td>{{ $row['municipio'] }}</td><td class="num">{{ number_format($row['count'], 0, ',', '.') }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
</body>
</html>
