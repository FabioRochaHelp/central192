<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>{{ $document->title() }} — {{ $document->talaoLabel() }}</title>
    @include('operations.documents.partials.incident-final-report-styles')
    <style>
        @page { margin: 28px 32px; }
        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            color: #18181b;
            line-height: 1.45;
            margin: 0;
        }
        /* DomPDF: fallback sem gradiente */
        .rpt-outcome {
            background: #fff7ed;
        }
        .rpt-header {
            margin: 0 0 20px;
            border-radius: 6px;
        }
    </style>
</head>
<body>
    @include('operations.documents.partials.incident-final-report-content', ['document' => $document])
</body>
</html>
