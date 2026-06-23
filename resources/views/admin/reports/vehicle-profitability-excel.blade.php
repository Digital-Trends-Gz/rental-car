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
        .profit { color: #047857; }
        .loss { color: #b91c1c; }
    </style>
</head>
<body>
@php
    $report = $vehicleProfitabilityReport ?? ['summary' => [], 'cars' => [], 'top_profitable' => [], 'least_profitable' => []];
    $summary = $report['summary'] ?? [];
    $cars = collect($report['cars'] ?? []);
    $topCars = collect($report['top_profitable'] ?? []);
    $leastCars = collect($report['least_profitable'] ?? []);
    $profitClass = fn ($row) => (float) ($row['net_profit'] ?? 0) < 0 ? 'loss' : 'profit';
@endphp
    <table>
        <tr>
            <td colspan="12" class="title">Vehicle Profitability Report - {{ $tenant?->name ?? config('app.name') }}</td>
        </tr>
        <tr>
            <td>Period</td>
            <td>{{ $periodLabel }}</td>
            <td>Branch</td>
            <td colspan="9">{{ $branchName }}</td>
        </tr>
        <tr>
            <td>Date From</td>
            <td>{{ $dateRange['start']->format('Y-m-d') }}</td>
            <td>Date To</td>
            <td colspan="9">{{ $dateRange['end']->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td>Generated At</td>
            <td colspan="11">{{ $generatedAt->format('Y-m-d H:i') }}</td>
        </tr>

        <tr><td colspan="12" class="section">Summary</td></tr>
        <tr>
            <th>Metric</th>
            <th colspan="3">Formatted Value</th>
            <th colspan="8">Numeric Value</th>
        </tr>
        <tr>
            <td>Total revenue</td>
            <td colspan="3" class="amount">{{ $summary['formatted_total_revenue'] ?? '$0.00' }}</td>
            <td colspan="8">{{ $canViewFinancials ? ($summary['total_revenue'] ?? 0) : '' }}</td>
        </tr>
        <tr>
            <td>Total costs</td>
            <td colspan="3" class="amount">{{ $summary['formatted_total_costs'] ?? '$0.00' }}</td>
            <td colspan="8">{{ $canViewFinancials ? ($summary['total_costs'] ?? 0) : '' }}</td>
        </tr>
        <tr>
            <td>Net profit</td>
            <td colspan="3" class="amount">{{ $summary['formatted_net_profit'] ?? '$0.00' }}</td>
            <td colspan="8">{{ $canViewFinancials ? ($summary['net_profit'] ?? 0) : '' }}</td>
        </tr>
        <tr>
            <td>Average revenue per car</td>
            <td colspan="3" class="amount">{{ $summary['formatted_average_revenue_per_car'] ?? '$0.00' }}</td>
            <td colspan="8">{{ $canViewFinancials ? ($summary['average_revenue_per_car'] ?? 0) : '' }}</td>
        </tr>
        <tr>
            <td>Profitable cars</td>
            <td colspan="11">{{ $summary['profitable_cars'] ?? 0 }}</td>
        </tr>
        <tr>
            <td>Loss-making cars</td>
            <td colspan="11">{{ $summary['loss_making_cars'] ?? 0 }}</td>
        </tr>

        <tr><td colspan="12" class="section">Vehicle Profitability Details</td></tr>
        <tr>
            <th>Car ID</th>
            <th>Car</th>
            <th>Plate</th>
            <th>Status</th>
            <th>Revenue</th>
            <th>Damage cost</th>
            <th>Maintenance cost</th>
            <th>Violation cost</th>
            <th>Total costs</th>
            <th>Net profit</th>
            <th>Reservations</th>
            <th>Utilization</th>
        </tr>
        @forelse($cars as $car)
            <tr>
                <td>{{ $car['car_id'] ?? '' }}</td>
                <td>{{ $car['car_name'] ?? '-' }}</td>
                <td>{{ $car['license_plate'] ?? '-' }}</td>
                <td>{{ $car['status'] ?? '-' }}</td>
                <td class="amount">{{ $canViewFinancials ? ($car['revenue'] ?? 0) : ($car['formatted_revenue'] ?? '') }}</td>
                <td class="amount">{{ $canViewFinancials ? ($car['damage_cost'] ?? 0) : ($car['formatted_damage_cost'] ?? '') }}</td>
                <td class="amount">{{ $canViewFinancials ? ($car['maintenance_cost'] ?? 0) : ($car['formatted_maintenance_cost'] ?? '') }}</td>
                <td class="amount">{{ $canViewFinancials ? ($car['violation_cost'] ?? 0) : ($car['formatted_violation_cost'] ?? '') }}</td>
                <td class="amount">{{ $canViewFinancials ? ($car['total_costs'] ?? 0) : ($car['formatted_total_costs'] ?? '') }}</td>
                <td class="amount {{ $profitClass($car) }}">{{ $canViewFinancials ? ($car['net_profit'] ?? 0) : ($car['formatted_net_profit'] ?? '') }}</td>
                <td>{{ $car['reservations_count'] ?? 0 }}</td>
                <td>{{ $car['utilization_rate'] ?? 0 }}%</td>
            </tr>
        @empty
            <tr>
                <td colspan="12">No vehicle profitability data for this period.</td>
            </tr>
        @endforelse

        <tr><td colspan="12" class="section">Top profitable cars</td></tr>
        <tr>
            <th>Car</th>
            <th colspan="11">Net profit</th>
        </tr>
        @forelse($topCars as $car)
            <tr>
                <td>{{ $car['car_name'] ?? '-' }}</td>
                <td colspan="11" class="amount profit">{{ $car['formatted_net_profit'] ?? '$0.00' }}</td>
            </tr>
        @empty
            <tr><td colspan="12">No data.</td></tr>
        @endforelse

        <tr><td colspan="12" class="section">Least profitable cars</td></tr>
        <tr>
            <th>Car</th>
            <th colspan="11">Net profit</th>
        </tr>
        @forelse($leastCars as $car)
            <tr>
                <td>{{ $car['car_name'] ?? '-' }}</td>
                <td colspan="11" class="amount {{ $profitClass($car) }}">{{ $car['formatted_net_profit'] ?? '$0.00' }}</td>
            </tr>
        @empty
            <tr><td colspan="12">No data.</td></tr>
        @endforelse
    </table>
</body>
</html>
