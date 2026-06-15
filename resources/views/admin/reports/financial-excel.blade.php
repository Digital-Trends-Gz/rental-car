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
        .amount { font-weight: bold; }
    </style>
</head>
<body>
    <table>
        <tr>
            <td colspan="5" class="title">Financial Report - {{ $tenant?->name ?? config('app.name') }}</td>
        </tr>
        <tr>
            <td>Period</td>
            <td>{{ $periodLabel }}</td>
            <td>Branch</td>
            <td colspan="2">{{ $branchName }}</td>
        </tr>
        <tr>
            <td>Date From</td>
            <td>{{ $dateRange['start']->format('Y-m-d') }}</td>
            <td>Date To</td>
            <td colspan="2">{{ $dateRange['end']->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td>Generated At</td>
            <td colspan="4">{{ $generatedAt->format('Y-m-d H:i') }}</td>
        </tr>

        @foreach($financialReportSections as $section)
            <tr>
                <td colspan="5" class="section">{{ $section['title']['en'] }} / {{ $section['title']['ar'] }}</td>
            </tr>
            <tr>
                <th>Metric</th>
                <th>Arabic</th>
                <th>Amount</th>
                <th>Records</th>
                <th>Numeric Value</th>
            </tr>
            @foreach($section['items'] as $item)
                <tr>
                    <td>{{ $item['en'] }}</td>
                    <td>{{ $item['ar'] }}</td>
                    <td class="amount">{{ $item['formatted'] ?? '$0.00' }}</td>
                    <td>{{ $item['count'] ?? 0 }}</td>
                    <td>{{ $canViewFinancials ? ($item['value'] ?? 0) : '' }}</td>
                </tr>
            @endforeach
        @endforeach
    </table>
</body>
</html>
