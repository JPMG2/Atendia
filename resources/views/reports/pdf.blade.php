{{-- The one PDF every report prints with. dompdf reads no CSS variables: the palette
arrives as data from PdfExporter, never typed here. The page MARGIN repeats on every
page, and the running header and footer live inside it, so page 2 never starts at the edge. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8" />
    <title>{{ $report->title }}</title>
    <style>
        @page {
            margin: 112px 40px 64px 40px;
        }
        body {
            font-family:
                DejaVu Sans,
                sans-serif;
            font-size: 9.5px;
            color: {{ $palette['ink'] }};
            margin: 0;
        }

        .run-head {
            position: fixed;
            top: -86px;
            left: 0;
            right: 0;
            height: 56px;
            border-bottom: 2px solid {{ $palette['brand'] }};
        }
        .run-head table {
            width: 100%;
            border-collapse: collapse;
        }
        .run-head td {
            vertical-align: middle;
            padding: 0 0 8px;
        }
        .brand-name {
            font-size: 15px;
            font-weight: bold;
        }
        .brand-slogan {
            font-size: 8px;
            color: {{ $palette['muted'] }};
            margin-top: 2px;
        }
        .run-right {
            text-align: right;
            font-size: 8.5px;
            color: {{ $palette['muted'] }};
        }
        .run-right b {
            color: {{ $palette['ink'] }};
        }
        .run-foot {
            position: fixed;
            bottom: -40px;
            left: 0;
            right: 0;
            height: 28px;
            border-top: 1px solid {{ $palette['line'] }};
            padding-top: 7px;
            font-size: 8px;
            color: {{ $palette['muted'] }};
        }

        .eyebrow {
            font-size: 8px;
            font-weight: bold;
            letter-spacing: 1.4px;
            color: {{ $palette['brand'] }};
            text-transform: uppercase;
        }
        h1 {
            font-size: 24px;
            margin: 2px 0 14px;
        }
        .when {
            font-size: 8.5px;
            color: {{ $palette['muted'] }};
            margin: -9px 0 14px;
        }
        .boxes {
            width: 100%;
            border-collapse: separate;
            border-spacing: 0;
            margin-bottom: 14px;
        }
        .boxes td {
            vertical-align: top;
            width: 50%;
        }
        .box {
            border: 1px solid {{ $palette['line'] }};
            border-radius: 8px;
            padding: 9px 11px;
        }
        .lbl {
            font-size: 7.5px;
            font-weight: bold;
            letter-spacing: 1.2px;
            color: {{ $palette['muted'] }};
            text-transform: uppercase;
        }
        .big {
            font-size: 13px;
            font-weight: bold;
            margin: 3px 0 5px;
        }
        .kv {
            width: 100%;
            font-size: 9px;
            border-collapse: collapse;
        }
        .kv td {
            padding: 1.5px 0;
        }
        .kv td:first-child {
            width: 76px;
            color: {{ $palette['muted'] }};
        }
        .chip {
            display: inline-block;
            background: {{ $palette['brandSoft'] }};
            color: {{ $palette['brandInk'] }};
            padding: 2px 7px;
            border-radius: 9px;
            font-size: 8.5px;
            font-weight: bold;
            margin: 0 3px 3px 0;
        }

        table.data {
            width: 100%;
            border-collapse: collapse;
        }
        table.data thead {
            display: table-header-group;
        }
        table.data th {
            text-align: left;
            font-size: 7.8px;
            letter-spacing: 0.8px;
            text-transform: uppercase;
            color: {{ $palette['muted'] }};
            border-bottom: 2px solid {{ $palette['brand'] }};
            padding: 6px 7px;
        }
        table.data td {
            padding: 6px 7px;
            border-bottom: 1px solid {{ $palette['line'] }};
        }
        table.data tr {
            page-break-inside: avoid;
        }
        table.data tr:nth-child(even) td {
            background: {{ $palette['zebra'] }};
        }
        table.data td:first-child {
            font-weight: bold;
        }
        .count {
            margin-top: 8px;
            font-size: 8.5px;
            color: {{ $palette['muted'] }};
        }
    </style>
</head>
<body>
    <div class="run-head">
        <table>
            <tr>
                <td style="width: 44px"><img src="{{ $letterhead->logo }}" width="38" height="38" /></td>
                <td>
                    <div class="brand-name">{{ $letterhead->brand }}</div>
                    @if ($letterhead->tagline !== '')
                        <div class="brand-slogan">{{ $letterhead->tagline }}</div>
                    @endif
                </td>
                <td class="run-right">
                    <b>{{ $report->title }}</b>
                    @if ($letterhead->audience === \App\Enums\ReportAudience::Client)
                        <span>&middot; {{ $letterhead->subjectName }}</span>
                    @endif
                    <br />
                    {{ __('reports.generated', ['date' => $letterhead->generatedAt]) }}
                </td>
            </tr>
        </table>
    </div>

    <div class="run-foot">{{ $letterhead->contactLine }}</div>

    <div class="eyebrow">{{ __('reports.eyebrow') }}</div>
    <h1>{{ $report->title }}</h1>
    @if ($report->subtitle)
        <p class="when">{{ $report->subtitle }}</p>
    @endif

    <table class="boxes">
        <tr>
            <td style="padding-right: 6px">
                <div class="box">
                    <div class="lbl">{{ $letterhead->subjectLabel }}</div>
                    <div class="big">{{ $letterhead->subjectName }}</div>
                    <table class="kv">
                        @foreach ($letterhead->subjectLines as $line)
                            <tr>
                                <td>{{ $line['label'] }}</td>
                                <td>{{ $line['value'] }}</td>
                            </tr>
                        @endforeach
                    </table>
                </div>
            </td>
            <td style="padding-left: 6px">
                <div class="box">
                    <div class="lbl">{{ __('reports.scope.title') }}</div>
                    <div style="margin: 5px 0 7px">
                        @forelse ($report->filters as $filter)
                            <span class="chip">{{ $filter['label'] }}: {{ $filter['value'] }}</span>
                        @empty
                            <span class="chip">{{ __('reports.scope.none') }}</span>
                        @endforelse
                    </div>
                    <table class="kv">
                        <tr>
                            <td>{{ __('reports.scope.records') }}</td>
                            <td>
                                @if ($report->total !== null && $report->isFiltered)
                                    {{ __('reports.scope.of', ['shown' => count($report->rows), 'total' => $report->total]) }}
                                @else
                                    {{ count($report->rows) }}
                                @endif
                            </td>
                        </tr>
                        <tr>
                            <td>{{ __('reports.scope.timezone') }}</td>
                            <td>{{ $letterhead->timezoneLabel }}</td>
                        </tr>
                    </table>
                </div>
            </td>
        </tr>
    </table>

    <table class="data">
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

    <div class="count">
        {{ trans_choice('reports.records', count($report->rows), ['count' => count($report->rows)]) }} · {{ $report->isFiltered ? __('reports.scope.filtered') : __('reports.scope.none_lower') }}
    </div>
</body>
</html>
