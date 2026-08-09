<?php

namespace App\Http\Controllers\SuperAdmin;

use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\StreamedResponse;

class RevenueTransactionsController
{
    public function index(Request $request)
    {
        [$rowsQuery, $filters] = $this->buildFilteredRowsQuery($request);

        $summary = (clone $rowsQuery)
            ->selectRaw(
                "COUNT(*) as total_rows,
                 SUM(CASE WHEN source = 'booking' AND LOWER(status) = 'completed' THEN amount ELSE 0 END) as booking_revenue,
                 SUM(CASE WHEN source = 'subscription' AND LOWER(status) IN ('paid', 'active', 'trialing') THEN amount ELSE 0 END) as subscription_revenue,
                 SUM(CASE
                    WHEN source = 'booking' AND LOWER(status) = 'completed' THEN amount
                    WHEN source = 'subscription' AND LOWER(status) IN ('paid', 'active', 'trialing') THEN amount
                    ELSE 0
                 END) as total_revenue"
            )
            ->first();

        $rows = (clone $rowsQuery)
            ->orderByDesc('paid_at')
            ->paginate(25)
            ->withQueryString();

        $revenueByCurrency = (clone $rowsQuery)
            ->selectRaw(
                "UPPER(COALESCE(currency, 'USD')) as currency,
                 SUM(CASE
                    WHEN source = 'booking' AND LOWER(status) = 'completed' THEN amount
                    WHEN source = 'subscription' AND LOWER(status) IN ('paid', 'active', 'trialing') THEN amount
                    ELSE 0
                 END) as revenue"
            )
            ->groupByRaw("UPPER(COALESCE(currency, 'USD'))")
            ->orderBy('currency')
            ->get();

        $statuses = $this->collectStatuses();

        $tenants = DB::table('tenants')
            ->select('id', 'name', 'slug')
            ->orderBy('name')
            ->get();

        return Inertia::render('SuperAdmin/Revenue/Transactions', [
            'rows' => $rows,
            'summary' => [
                'total_rows' => (int) ($summary->total_rows ?? 0),
                'booking_revenue' => (float) ($summary->booking_revenue ?? 0),
                'subscription_revenue' => (float) ($summary->subscription_revenue ?? 0),
                'total_revenue' => (float) ($summary->total_revenue ?? 0),
            ],
            'revenueByCurrency' => $revenueByCurrency,
            'statuses' => $statuses,
            'tenants' => $tenants,
            'filters' => $filters,
        ]);
    }

    public function exportCsv(Request $request): StreamedResponse
    {
        [$rowsQuery, $filters] = $this->buildFilteredRowsQuery($request);

        $rows = (clone $rowsQuery)
            ->orderByDesc('paid_at')
            ->get();

        $fileName = 'financial-report-' . now()->format('Y-m-d_H-i') . '.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }

            fputcsv($out, [
                'Source',
                'Date',
                'Tenant',
                'Tenant Slug',
                'User Name',
                'User Email',
                'Status',
                'Method',
                'Amount',
                'Currency',
                'Reference',
                'Context',
                'Plan',
            ]);

            foreach ($rows as $row) {
                fputcsv($out, [
                    $row->source,
                    $row->paid_at,
                    $row->tenant_name,
                    $row->tenant_slug,
                    $row->user_name,
                    $row->user_email,
                    $row->status,
                    $row->payment_method,
                    number_format((float) $row->amount, 2, '.', ''),
                    strtoupper((string) $row->currency),
                    $row->reference,
                    $row->context_reference,
                    $row->plan_name,
                ]);
            }

            fclose($out);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportPdf(Request $request)
    {
        [$rowsQuery, $filters] = $this->buildFilteredRowsQuery($request);

        $rows = (clone $rowsQuery)
            ->orderByDesc('paid_at')
            ->get();

        $summary = (clone $rowsQuery)
            ->selectRaw(
                "COUNT(*) as total_rows,
                 SUM(CASE WHEN source = 'booking' AND LOWER(status) = 'completed' THEN amount ELSE 0 END) as booking_revenue,
                 SUM(CASE WHEN source = 'subscription' AND LOWER(status) IN ('paid', 'active', 'trialing') THEN amount ELSE 0 END) as subscription_revenue,
                 SUM(CASE
                    WHEN source = 'booking' AND LOWER(status) = 'completed' THEN amount
                    WHEN source = 'subscription' AND LOWER(status) IN ('paid', 'active', 'trialing') THEN amount
                    ELSE 0
                 END) as total_revenue"
            )
            ->first();

        $pdf = Pdf::loadView('superadmin.revenue.transactions-pdf', [
            'rows' => $rows,
            'filters' => $filters,
            'summary' => [
                'total_rows' => (int) ($summary->total_rows ?? 0),
                'booking_revenue' => (float) ($summary->booking_revenue ?? 0),
                'subscription_revenue' => (float) ($summary->subscription_revenue ?? 0),
                'total_revenue' => (float) ($summary->total_revenue ?? 0),
            ],
            'generatedAt' => now(),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('financial-report-' . now()->format('Y-m-d_H-i') . '.pdf');
    }

    private function buildFilteredRowsQuery(Request $request): array
    {
        $search = trim((string) $request->query('search', ''));
        $source = strtolower(trim((string) $request->query('source', 'all')));
        $status = strtolower(trim((string) $request->query('status', '')));
        $tenantId = (int) $request->query('tenant_id', 0);
        $dateFrom = trim((string) $request->query('date_from', ''));
        $dateTo = trim((string) $request->query('date_to', ''));

        $bookingQuery = DB::table('payments as payments')
            ->leftJoin('reservations as reservations', 'reservations.id', '=', 'payments.reservation_id')
            ->leftJoin('users as users', 'users.id', '=', 'payments.user_id')
            ->leftJoin('tenants as tenants', 'tenants.id', '=', 'payments.tenant_id')
            ->leftJoin('plans as plans', 'plans.id', '=', 'tenants.plan_id')
            ->whereNull('payments.deleted_at')
            ->selectRaw("
                CONCAT('booking-', payments.id) as row_id,
                'booking' as source,
                payments.id as source_id,
                payments.tenant_id as tenant_id,
                tenants.name as tenant_name,
                tenants.slug as tenant_slug,
                users.name as user_name,
                users.email as user_email,
                LOWER(COALESCE(payments.status, 'pending')) as status,
                COALESCE(payments.payment_method, '-') as payment_method,
                CAST(payments.amount AS DECIMAL(12,2)) as amount,
                COALESCE(payments.currency, 'USD') as currency,
                COALESCE(payments.transaction_id, payments.payment_number, CONCAT('PAY-', payments.id)) as reference,
                COALESCE(reservations.reservation_number, '-') as context_reference,
                COALESCE(plans.name, '-') as plan_name,
                COALESCE(payments.processed_at, payments.created_at) as paid_at
            ");

        $subscriptionQuery = DB::table('subscriptions as subscriptions')
            ->join('users as users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('tenants as tenants', 'tenants.id', '=', 'users.tenant_id')
            ->leftJoin('plans as plans', 'plans.id', '=', 'tenants.plan_id')
            ->selectRaw("
                CONCAT('subscription-', subscriptions.id) as row_id,
                'subscription' as source,
                subscriptions.id as source_id,
                users.tenant_id as tenant_id,
                tenants.name as tenant_name,
                tenants.slug as tenant_slug,
                users.name as user_name,
                users.email as user_email,
                LOWER(COALESCE(subscriptions.stripe_status, 'pending')) as status,
                COALESCE(subscriptions.payment_method, '-') as payment_method,
                CAST(COALESCE(subscriptions.amount_paid, 0) AS DECIMAL(12,2)) as amount,
                COALESCE(subscriptions.currency, 'USD') as currency,
                COALESCE(subscriptions.stripe_id, CONCAT('SUB-', subscriptions.id)) as reference,
                COALESCE(subscriptions.type, '-') as context_reference,
                COALESCE(plans.name, '-') as plan_name,
                COALESCE(subscriptions.paid_at, subscriptions.updated_at, subscriptions.created_at) as paid_at
            ");

        $baseRows = DB::query()->fromSub(
            $bookingQuery->unionAll($subscriptionQuery),
            'financial_rows'
        );

        $filteredRows = $baseRows
            ->when(in_array($source, ['booking', 'subscription'], true), fn ($query) => $query->where('source', $source))
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->when($tenantId > 0, fn ($query) => $query->where('tenant_id', $tenantId))
            ->when($search !== '', function ($query) use ($search) {
                $like = '%' . $search . '%';
                $query->where(function ($nested) use ($like) {
                    $nested->orWhere('tenant_name', 'like', $like)
                        ->orWhere('tenant_slug', 'like', $like)
                        ->orWhere('user_name', 'like', $like)
                        ->orWhere('user_email', 'like', $like)
                        ->orWhere('reference', 'like', $like)
                        ->orWhere('context_reference', 'like', $like)
                        ->orWhere('plan_name', 'like', $like)
                        ->orWhere('payment_method', 'like', $like);
                });
            })
            ->when($this->isValidDate($dateFrom), fn ($query) => $query->whereDate('paid_at', '>=', $dateFrom))
            ->when($this->isValidDate($dateTo), fn ($query) => $query->whereDate('paid_at', '<=', $dateTo));

        return [
            $filteredRows,
            [
                'search' => $search,
                'source' => $source,
                'status' => $status,
                'tenant_id' => $tenantId > 0 ? $tenantId : null,
                'date_from' => $this->isValidDate($dateFrom) ? $dateFrom : null,
                'date_to' => $this->isValidDate($dateTo) ? $dateTo : null,
            ],
        ];
    }

    private function collectStatuses(): array
    {
        $paymentStatuses = DB::table('payments')
            ->whereNotNull('status')
            ->distinct()
            ->pluck('status')
            ->map(fn ($status) => strtolower((string) $status))
            ->all();

        $subscriptionStatuses = DB::table('subscriptions')
            ->whereNotNull('stripe_status')
            ->distinct()
            ->pluck('stripe_status')
            ->map(fn ($status) => strtolower((string) $status))
            ->all();

        return collect(array_merge($paymentStatuses, $subscriptionStatuses))
            ->filter()
            ->unique()
            ->sort()
            ->values()
            ->all();
    }

    private function isValidDate(string $date): bool
    {
        if ($date === '') {
            return false;
        }

        try {
            return Carbon::parse($date)->format('Y-m-d') === $date;
        } catch (\Throwable) {
            return false;
        }
    }
}
