<!doctype html>
<html>
<head>
    <meta charset="utf-8">
    <style>
        table { border-collapse: collapse; }
        th, td { border: 1px solid #cccccc; padding: 6px; }
        th { background: #eef2ff; font-weight: bold; }
        .title { font-size: 18px; font-weight: bold; color: #1e3a8a; }
        .section { background: #dbeafe; font-weight: bold; color: #1e3a8a; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="4" class="title">Executive Report - {{ $tenant?->name ?? config('app.name') }}</td>
        </tr>
        <tr>
            <td>Period</td>
            <td>{{ $periodLabel }}</td>
            <td>Branch</td>
            <td>{{ $branchName }}</td>
        </tr>
        <tr>
            <td>Date From</td>
            <td>{{ $dateRange['start']->format('Y-m-d') }}</td>
            <td>Date To</td>
            <td>{{ $dateRange['end']->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td>Generated At</td>
            <td colspan="3">{{ $generatedAt->format('Y-m-d H:i') }}</td>
        </tr>

        <tr><td colspan="4" class="section">Financial Report / التقرير المالي</td></tr>
        @foreach($financialReportSections as $section)
            <tr>
                <th colspan="4">{{ $section['title']['ar'] }} / {{ $section['title']['en'] }}</th>
            </tr>
            @foreach($section['items'] as $item)
                <tr>
                    <td colspan="2">&bull; {{ $item['ar'] }}</td>
                    <td colspan="2">{{ $item['en'] }}</td>
                </tr>
            @endforeach
        @endforeach

        <tr><td colspan="4" class="section">Financial Summary</td></tr>
        <tr>
            <th>Metric</th>
            <th>Formatted Value</th>
            <th>Numeric Value</th>
            <th>Color</th>
        </tr>
        @foreach($report['financial'] as $metric)
            <tr>
                <td>{{ $metric['label'] }}</td>
                <td>{{ $metric['formatted'] }}</td>
                <td>{{ $canViewFinancials ? $metric['value'] : '' }}</td>
                <td>{{ $metric['color'] }}</td>
            </tr>
        @endforeach

        <tr><td colspan="4" class="section">Operations Summary</td></tr>
        <tr>
            <th>Metric</th>
            <th>Formatted Value</th>
            <th>Numeric Value</th>
            <th>Color</th>
        </tr>
        @foreach($report['operations'] as $metric)
            <tr>
                <td>{{ $metric['label'] }}</td>
                <td>{{ $metric['formatted'] }}</td>
                <td>{{ $metric['value'] }}</td>
                <td>{{ $metric['color'] }}</td>
            </tr>
        @endforeach

        <tr><td colspan="4" class="section">Action Alerts</td></tr>
        <tr>
            <th>Alert</th>
            <th>Description</th>
            <th>Count</th>
            <th>Severity</th>
        </tr>
        @foreach($report['alerts'] as $alert)
            <tr>
                <td>{{ $alert['label'] }}</td>
                <td>{{ $alert['description'] }}</td>
                <td>{{ $alert['value'] }}</td>
                <td>{{ $alert['severity'] }}</td>
            </tr>
        @endforeach
    </table>
</body>
</html>
