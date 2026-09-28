{{-- The one PDF layout every report prints with. dompdf reads no CSS variables:
the palette arrives as data from PdfExporter, never typed here. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>{{ $report->title }}</title>
    <style>
        @page {
            margin: 32px 36px;
        }
        body {
            font-family:
                DejaVu Sans,
                sans-serif;
            font-size: 11px;
            color: {{ $palette['ink'] }};
        }
        .brand {
            font-size: 13px;
            font-weight: bold;
            color: {{ $palette['brand'] }};
            letter-spacing: 0.5px;
        }
        h1 {
            font-size: 20px;
            margin: 6px 0 2px;
        }
        .sub {
            color: {{ $palette['muted'] }};
            margin: 0 0 16px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
        }
        th {
            text-align: left;
            font-size: 10px;
            text-transform: uppercase;
            letter-spacing: 0.6px;
            color: {{ $palette['muted'] }};
            border-bottom: 2px solid {{ $palette['brand'] }};
            padding: 6px 8px;
        }
        td {
            padding: 7px 8px;
            border-bottom: 1px solid {{ $palette['line'] }};
        }
        tr:nth-child(even) td {
            background: {{ $palette['zebra'] }};
        }
        .foot {
            position: fixed;
            bottom: -16px;
            left: 0;
            right: 0;
            font-size: 9px;
            color: {{ $palette['muted'] }};
        }
    </style>
</head>
<body>
    <div class="brand">AtendIa</div>
    <h1>{{ $report->title }}</h1>
    @if ($report->subtitle)
        <p class="sub">{{ $report->subtitle }}</p>
    @endif

    <table>
        <thead>
            <tr>
                @foreach ($report->columns as $column)
                    <th>{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody>
            @foreach ($report->rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td>{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>

    <div class="foot">{{ __('reports.generated', ['date' => $generatedAt]) }}</div>
</body>
</html>
