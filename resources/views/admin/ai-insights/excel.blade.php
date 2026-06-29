<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>AI Insights Raw Data Report</title>
    <style>
        table {
            border-collapse: collapse;
            width: 100%;
        }
        th, td {
            border: 1px solid #cbd5e1;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f1f5f9;
            font-weight: bold;
        }
        .header-cell {
            font-size: 16px;
            font-weight: bold;
            color: #1e3a8a;
            text-align: center;
        }
    </style>
</head>
<body>
    <table>
        <!-- Company Info Header -->
        <tr>
            <td colspan="5" class="header-cell">
                {{ $companyName }} - AI Insights Export Data
            </td>
        </tr>
        <tr>
            <td colspan="5">
                <strong>Generated At:</strong> {{ $generatedAt }}
            </td>
        </tr>
        <tr>
            <td colspan="5">
                <strong>Period:</strong> {{ $report['period_start'] }} to {{ $report['period_end'] }} ({{ $report['period'] }})
            </td>
        </tr>
        <tr>
            <td colspan="5">
                <strong>Branch:</strong> {{ $report['branch_name'] ?: 'All Branches' }}
            </td>
        </tr>
        <tr>
            <td colspan="5"></td>
        </tr>

        <!-- Pricing Opportunities -->
        <tr>
            <td colspan="5" style="background-color: #dcfce7; font-weight: bold; font-size: 14px;">
                Pricing Opportunities (+%)
            </td>
        </tr>
        <tr>
            <th>Car ID</th>
            <th>Car Name</th>
            <th>License Plate</th>
            <th>Current Rate</th>
            <th>Suggested Increase (%)</th>
        </tr>
        @if(isset($report['internal_payload']['price_opportunities']) && count($report['internal_payload']['price_opportunities']))
            @foreach($report['internal_payload']['price_opportunities'] as $item)
                <tr>
                    <td>{{ $item['car_id'] ?? '-' }}</td>
                    <td>{{ $item['car_name'] ?? '-' }}</td>
                    <td>{{ $item['license_plate'] ?? '-' }}</td>
                    <td>{{ $item['formatted_current_price'] ?? $item['current_price'] ?? 0 }}</td>
                    <td>+{{ $item['suggested_increase_percent'] ?? 0 }}%</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="5">No opportunities detected.</td>
            </tr>
        @endif
        <tr>
            <td colspan="5"></td>
        </tr>

        <!-- Unprofitable Cars -->
        <tr>
            <td colspan="5" style="background-color: #fee2e2; font-weight: bold; font-size: 14px;">
                Unprofitable Cars
            </td>
        </tr>
        <tr>
            <th>Car ID</th>
            <th>Car Name</th>
            <th>License Plate</th>
            <th>Net Profit</th>
            <th>Margin (%)</th>
        </tr>
        @if(isset($report['internal_payload']['unprofitable_cars']) && count($report['internal_payload']['unprofitable_cars']))
            @foreach($report['internal_payload']['unprofitable_cars'] as $item)
                <tr>
                    <td>{{ $item['car_id'] ?? '-' }}</td>
                    <td>{{ $item['car_name'] ?? '-' }}</td>
                    <td>{{ $item['license_plate'] ?? '-' }}</td>
                    <td>{{ $item['formatted_net_profit'] ?? $item['net_profit'] ?? 0 }}</td>
                    <td>{{ $item['profit_margin'] ?? 0 }}%</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="5">No unprofitable cars detected.</td>
            </tr>
        @endif
        <tr>
            <td colspan="5"></td>
        </tr>

        <!-- High-Risk Customers -->
        <tr>
            <td colspan="5" style="background-color: #fef3c7; font-weight: bold; font-size: 14px;">
                High-Risk Customers
            </td>
        </tr>
        <tr>
            <th>Customer Ref</th>
            <th>Risk Score (0-100)</th>
            <th>Reservations</th>
            <th>Overdue Contracts</th>
            <th>Unpaid Balance</th>
        </tr>
        @if(isset($report['internal_payload']['high_risk_customers']) && count($report['internal_payload']['high_risk_customers']))
            @foreach($report['internal_payload']['high_risk_customers'] as $item)
                <tr>
                    <td>{{ $item['name'] ?? $item['email'] ?? 'Customer' }}</td>
                    <td>{{ $item['score'] ?? 0 }}/100</td>
                    <td>{{ $item['reservations_count'] ?? 0 }}</td>
                    <td>{{ $item['overdue_contracts_count'] ?? 0 }}</td>
                    <td>{{ $item['formatted_unpaid_amount'] ?? $item['unpaid_amount'] ?? 0 }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="5">No high risk customers detected.</td>
            </tr>
        @endif
        <tr>
            <td colspan="5"></td>
        </tr>

        <!-- Uncollected Losses -->
        <tr>
            <td colspan="5" style="background-color: #ffe4e6; font-weight: bold; font-size: 14px;">
                Uncollected Losses Breakdown
            </td>
        </tr>
        <tr>
            <th colspan="3">Loss Item / Label</th>
            <th colspan="2">Amount</th>
        </tr>
        @if(isset($report['internal_payload']['uncollected_losses']) && count($report['internal_payload']['uncollected_losses']))
            @foreach($report['internal_payload']['uncollected_losses'] as $item)
                <tr>
                    <td colspan="3">{{ $item['label'] ?? $item['key'] ?? '-' }}</td>
                    <td colspan="2">{{ $item['formatted_amount'] ?? $item['amount'] ?? 0 }}</td>
                </tr>
            @endforeach
        @else
            <tr>
                <td colspan="5">No uncollected losses items.</td>
            </tr>
        @endif
    </table>
</body>
</html>
