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
            <td colspan="3" class="title">Reservations Report - {{ $tenant?->name ?? config('app.name') }}</td>
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

        <tr>
            <td colspan="3" class="section">Reservations Summary / ملخص الحجوزات</td>
        </tr>
        <tr>
            <th>Metric</th>
            <th>Arabic</th>
            <th>Count</th>
        </tr>
        <tr>
            <td>Total Reservations</td>
            <td>إجمالي الحجوزات</td>
            <td class="amount">{{ $reservationsReport['summary']['total'] }}</td>
        </tr>
        <tr>
            <td>Confirmed</td>
            <td>الحجوزات المؤكدة</td>
            <td class="amount">{{ $reservationsReport['summary']['confirmed'] }}</td>
        </tr>
        <tr>
            <td>Canceled</td>
            <td>الحجوزات الملغاة</td>
            <td class="amount">{{ $reservationsReport['summary']['canceled'] }}</td>
        </tr>
        <tr>
            <td>No Show</td>
            <td>No Show</td>
            <td class="amount">{{ $reservationsReport['summary']['no_show'] }}</td>
        </tr>
        <tr>
            <td>Completed</td>
            <td>الحجوزات المكتملة</td>
            <td class="amount">{{ $reservationsReport['summary']['completed'] }}</td>
        </tr>

        <tr>
            <td colspan="3" class="section">Key Performance Indicators / مؤشرات الأداء الرئيسية</td>
        </tr>
        <tr>
            <th>Metric</th>
            <th>Arabic</th>
            <th>Value</th>
        </tr>
        <tr>
            <td>Average Value</td>
            <td>متوسط قيمة الحجز</td>
            <td class="amount">{{ $reservationsReport['kpis']['average_value']['formatted'] }}</td>
        </tr>
        <tr>
            <td>Cancellation Rate</td>
            <td>نسبة الإلغاء</td>
            <td class="amount">{{ $reservationsReport['kpis']['cancellation_rate']['formatted'] }}</td>
        </tr>
        <tr>
            <td>No-Show Rate</td>
            <td>نسبة No Show</td>
            <td class="amount">{{ $reservationsReport['kpis']['no_show_rate']['formatted'] }}</td>
        </tr>
    </table>
</body>
</html>
