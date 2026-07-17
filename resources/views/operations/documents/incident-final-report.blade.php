<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $document->title() }} — {{ $document->talaoLabel() }}</title>
    @include('operations.documents.partials.incident-final-report-styles')
    <style>
        body {
            margin: 0;
            font-family: ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            color: #18181b;
            background: #e4e4e7;
            line-height: 1.5;
        }

        .doc-toolbar {
            display: flex;
            flex-wrap: wrap;
            gap: 0.75rem;
            align-items: center;
            justify-content: space-between;
            padding: 0.875rem 1.5rem;
            background: rgba(255, 255, 255, 0.92);
            backdrop-filter: blur(8px);
            border-bottom: 1px solid #d4d4d8;
            position: sticky;
            top: 0;
            z-index: 20;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.06);
        }

        .doc-toolbar-title {
            font-size: 0.9375rem;
            font-weight: 600;
            color: #18181b;
        }

        .doc-toolbar-sub {
            font-size: 0.8125rem;
            color: #71717a;
            margin-top: 2px;
        }

        .doc-toolbar-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5rem;
        }

        .doc-btn {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            border: 1px solid #d4d4d8;
            background: #fff;
            color: #3f3f46;
            text-decoration: none;
            font-size: 0.8125rem;
            font-weight: 500;
            cursor: pointer;
            transition: background 0.15s, border-color 0.15s;
        }

        .doc-btn:hover {
            background: #fafafa;
            border-color: #a1a1aa;
        }

        .doc-btn-primary {
            background: #ea580c;
            border-color: #c2410c;
            color: #fff;
        }

        .doc-btn-primary:hover {
            background: #c2410c;
            border-color: #9a3412;
        }

        .doc-page {
            max-width: 880px;
            margin: 1.75rem auto 2.5rem;
            padding: 0 1rem;
        }

        .doc-sheet {
            background: #fff;
            border: 1px solid #d4d4d8;
            border-radius: 12px;
            padding: 2px;
            box-shadow: 0 4px 24px rgba(0, 0, 0, 0.08), 0 1px 3px rgba(0, 0, 0, 0.04);
            overflow: hidden;
        }

        .doc-sheet .rpt { padding: 0 22px 22px; }

        @media print {
            body { background: #fff; }
            .doc-toolbar { display: none !important; }
            .doc-page { margin: 0; padding: 0; max-width: none; }
            .doc-sheet {
                border: none;
                border-radius: 0;
                box-shadow: none;
                padding: 0;
            }
            .doc-sheet .rpt { padding: 0; }
            .rpt-header { border-radius: 0; margin: 0 0 20px; }
        }
    </style>
</head>
<body>
    <div class="doc-toolbar">
        <div>
            <div class="doc-toolbar-title">{{ $document->title() }}</div>
            <div class="doc-toolbar-sub">{{ $document->talaoLabel() }}</div>
        </div>
        <div class="doc-toolbar-actions">
            <button type="button" class="doc-btn doc-btn-primary" onclick="window.print()">{{ __('Imprimir') }}</button>
            <a class="doc-btn" href="{{ route('operations.incidents.final-report.document', ['incident' => $document->incident, 'download' => 1]) }}">
                {{ __('Baixar PDF') }}
            </a>
            <a class="doc-btn" href="{{ route('operations.incidents.show', $document->incident) }}">{{ __('Voltar') }}</a>
        </div>
    </div>

    <div class="doc-page">
        <article class="doc-sheet">
            @include('operations.documents.partials.incident-final-report-content', ['document' => $document])
        </article>
    </div>
</body>
</html>
