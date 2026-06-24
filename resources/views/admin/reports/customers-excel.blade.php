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
    $report = $customersReport ?? ['summary' => [], 'top_by_revenue' => [], 'top_by_contracts' => [], 'debtors' => [], 'overdue_customers' => []];
    $summary = $report['summary'] ?? [];
    $topByRevenue = collect($report['top_by_revenue'] ?? []);
    $topByContracts = collect($report['top_by_contracts'] ?? []);
    $debtors = collect($report['debtors'] ?? []);
    $overdueCustomers = collect($report['overdue_customers'] ?? []);
@endphp
    <table>
        <tr>
            <td colspan="8" class="title">Customers Report - {{ $tenant?->name ?? config('app.name') }}</td>
        </tr>
        <tr>
            <td>Period</td>
            <td>{{ $periodLabel }}</td>
            <td>Branch</td>
            <td colspan="5">{{ $branchName }}</td>
        </tr>
        <tr>
            <td>Date From</td>
            <td>{{ $dateRange['start']->format('Y-m-d') }}</td>
            <td>Date To</td>
            <td colspan="5">{{ $dateRange['end']->format('Y-m-d') }}</td>
        </tr>
        <tr>
            <td>Generated At</td>
            <td colspan="7">{{ $generatedAt->format('Y-m-d H:i') }}</td>
        </tr>

        <tr><td colspan="8" class="section">Summary</td></tr>
        <tr>
            <th>Metric</th>
            <th>Value</th>
            <th>Formatted Value</th>
            <th colspan="5">Notes</th>
        </tr>
        <tr>
            <td>New customers</td>
            <td>{{ $summary['new_customers'] ?? 0 }}</td>
            <td>{{ $summary['new_customers'] ?? 0 }}</td>
            <td colspan="5">Customers created during the selected period.</td>
        </tr>
        <tr>
            <td>Active customers</td>
            <td>{{ $summary['active_customers'] ?? 0 }}</td>
            <td>{{ $summary['active_customers'] ?? 0 }}</td>
            <td colspan="5">Currently active customer accounts.</td>
        </tr>
        <tr>
            <td>Repeat customers</td>
            <td>{{ $summary['repeat_customers'] ?? 0 }}</td>
            <td>{{ $summary['repeat_rate'] ?? 0 }}%</td>
            <td colspan="5">Customers with more than one reservation in the selected period.</td>
        </tr>
        <tr>
            <td>Total revenue</td>
            <td>{{ $canViewFinancials ? ($summary['total_revenue'] ?? 0) : '' }}</td>
            <td class="amount">{{ $summary['formatted_total_revenue'] ?? '$0.00' }}</td>
            <td colspan="5">Completed payments in the selected period.</td>
        </tr>
        <tr>
            <td>Debtors</td>
            <td>{{ $summary['debtors_count'] ?? 0 }}</td>
            <td class="amount">{{ $summary['formatted_total_outstanding'] ?? '$0.00' }}</td>
            <td colspan="5">Customers with outstanding balances.</td>
        </tr>
        <tr>
            <td>Overdue customers</td>
            <td>{{ $summary['overdue_customers_count'] ?? 0 }}</td>
            <td>{{ $summary['overdue_customers_count'] ?? 0 }}</td>
            <td colspan="5">Customers with active overdue contracts.</td>
        </tr>

        <tr><td colspan="8" class="section">Top customers by revenue</td></tr>
        <tr>
            <th>Customer ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Revenue</th>
            <th>Revenue formatted</th>
            <th>Payments</th>
            <th>Reservations</th>
            <th></th>
        </tr>
        @forelse($topByRevenue as $customer)
            <tr>
                <td>{{ $customer['customer_id'] ?? '' }}</td>
                <td>{{ $customer['name'] ?? '-' }}</td>
                <td>{{ $customer['email'] ?? '-' }}</td>
                <td class="amount">{{ $canViewFinancials ? ($customer['revenue'] ?? 0) : '' }}</td>
                <td class="amount">{{ $customer['formatted_revenue'] ?? '$0.00' }}</td>
                <td>{{ $customer['payments_count'] ?? 0 }}</td>
                <td>{{ $customer['reservations_count'] ?? 0 }}</td>
                <td></td>
            </tr>
        @empty
            <tr><td colspan="8">No revenue data for this period.</td></tr>
        @endforelse

        <tr><td colspan="8" class="section">Top customers by contracts</td></tr>
        <tr>
            <th>Customer ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Contracts</th>
            <th>Contract value</th>
            <th>Contract value formatted</th>
            <th colspan="2"></th>
        </tr>
        @forelse($topByContracts as $customer)
            <tr>
                <td>{{ $customer['customer_id'] ?? '' }}</td>
                <td>{{ $customer['name'] ?? '-' }}</td>
                <td>{{ $customer['email'] ?? '-' }}</td>
                <td>{{ $customer['contracts_count'] ?? 0 }}</td>
                <td class="amount">{{ $canViewFinancials ? ($customer['contract_value'] ?? 0) : '' }}</td>
                <td class="amount">{{ $customer['formatted_contract_value'] ?? '$0.00' }}</td>
                <td colspan="2"></td>
            </tr>
        @empty
            <tr><td colspan="8">No contract data for this period.</td></tr>
        @endforelse

        <tr><td colspan="8" class="section">Customers with debts</td></tr>
        <tr>
            <th>Customer ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Outstanding amount</th>
            <th>Outstanding formatted</th>
            <th>Reservations</th>
            <th>Return reports</th>
            <th></th>
        </tr>
        @forelse($debtors as $customer)
            <tr>
                <td>{{ $customer['customer_id'] ?? '' }}</td>
                <td>{{ $customer['name'] ?? '-' }}</td>
                <td>{{ $customer['email'] ?? '-' }}</td>
                <td class="amount">{{ $canViewFinancials ? ($customer['outstanding_amount'] ?? 0) : '' }}</td>
                <td class="amount">{{ $customer['formatted_outstanding_amount'] ?? '$0.00' }}</td>
                <td>{{ $customer['reservations_count'] ?? 0 }}</td>
                <td>{{ $customer['return_reports_count'] ?? 0 }}</td>
                <td></td>
            </tr>
        @empty
            <tr><td colspan="8">No customer debts found.</td></tr>
        @endforelse

        <tr><td colspan="8" class="section">Overdue customers</td></tr>
        <tr>
            <th>Customer ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Overdue contracts</th>
            <th colspan="4"></th>
        </tr>
        @forelse($overdueCustomers as $customer)
            <tr>
                <td>{{ $customer['customer_id'] ?? '' }}</td>
                <td>{{ $customer['name'] ?? '-' }}</td>
                <td>{{ $customer['email'] ?? '-' }}</td>
                <td>{{ $customer['overdue_contracts_count'] ?? 0 }}</td>
                <td colspan="4"></td>
            </tr>
        @empty
            <tr><td colspan="8">No overdue customers found.</td></tr>
        @endforelse
    </table>
</body>
</html>
