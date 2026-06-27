@php echo "\xEF\xBB\xBF"; @endphp
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
@php
    $report = $collectionsReport ?? ['summary' => [], 'aging_buckets' => [], 'debtors' => []];
    $summary = $report['summary'] ?? [];
    $buckets = collect($report['aging_buckets'] ?? []);
    $debtors = collect($report['debtors'] ?? []);
@endphp
    <table>
        <tr>
            <td colspan="10" class="title">Collections Report - {{ $tenant?->name ?? config('app.name') }}</td>
        </tr>
        <tr>
            <td>Period</td>
            <td>{{ $periodLabel }}</td>
            <td>Branch</td>
            <td colspan="7">{{ $branchName }}</td>
        </tr>
        <tr>
            <td>Generated At</td>
            <td colspan="9">{{ $generatedAt->format('Y-m-d H:i') }}</td>
        </tr>

        <tr><td colspan="10" class="section">Summary</td></tr>
        <tr>
            <th>Debtors</th>
            <th>Items</th>
            <th>Overdue Items</th>
            <th>Current Items</th>
            <th>Total Outstanding</th>
            <th colspan="5"></th>
        </tr>
        <tr>
            <td>{{ $summary['debtors_count'] ?? 0 }}</td>
            <td>{{ $summary['items_count'] ?? 0 }}</td>
            <td>{{ $summary['overdue_items_count'] ?? 0 }}</td>
            <td>{{ $summary['current_items_count'] ?? 0 }}</td>
            <td class="amount">{{ $summary['formatted_total_outstanding'] ?? '$0.00' }}</td>
            <td colspan="5"></td>
        </tr>

        <tr><td colspan="10" class="section">Aging Classification</td></tr>
        <tr>
            <th>Bucket</th>
            <th>Count</th>
            <th>Amount</th>
            <th colspan="7"></th>
        </tr>
        @foreach($buckets as $bucket)
            <tr>
                <td>{{ $bucket['label'] ?? '-' }}</td>
                <td>{{ $bucket['count'] ?? 0 }}</td>
                <td class="amount">{{ $bucket['formatted_amount'] ?? '$0.00' }}</td>
                <td colspan="7"></td>
            </tr>
        @endforeach

        <tr><td colspan="10" class="section">Debtors</td></tr>
        <tr>
            <th>Customer</th>
            <th>Email</th>
            <th>Reference</th>
            <th>Source</th>
            <th>Amount</th>
            <th>Due Date</th>
            <th>Days Overdue</th>
            <th>Bucket</th>
            <th>Car</th>
            <th>Branch</th>
        </tr>
        @forelse($debtors as $row)
            <tr>
                <td>{{ $row['customer_name'] ?? '-' }}</td>
                <td>{{ $row['customer_email'] ?? '-' }}</td>
                <td>{{ $row['reference'] ?? '-' }}</td>
                <td>{{ $row['source_label'] ?? '-' }}</td>
                <td class="amount">{{ $row['formatted_amount'] ?? '$0.00' }}</td>
                <td>{{ $row['due_date'] ?? '-' }}</td>
                <td>{{ $row['days_overdue'] ?? 0 }}</td>
                <td>{{ $row['bucket_label'] ?? '-' }}</td>
                <td>{{ $row['car_name'] ?? '-' }} / {{ $row['license_plate'] ?? '-' }}</td>
                <td>{{ $row['branch_name'] ?? '-' }}</td>
            </tr>
        @empty
            <tr><td colspan="10">No debtors found.</td></tr>
        @endforelse
    </table>
</body>
</html>
