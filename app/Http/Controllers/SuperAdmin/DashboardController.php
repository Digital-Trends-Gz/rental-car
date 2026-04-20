<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Models\Tenant;
use App\Models\User;
use App\Models\Reservation;
use App\Models\Payment;
use App\Models\Ticket;
use App\Models\SaasVisit;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController
{
    /**
     * Display the Super Admin dashboard with system-wide statistics.
     */
    public function index(): Response
    {
        // Get all tenants with their counts (bypass tenant scope)
        $tenants = Tenant::query()
            ->with('subscriptionPlan:id,name')
            ->withCount(['users', 'cars', 'reservations'])
            ->get();

        // System-wide statistics
        $stats = [
            'total_tenants' => $tenants->count(),
            'active_tenants' => $tenants->where('is_active', true)->count(),
            'total_users' => User::withoutGlobalScope('tenant')->count(),
            'total_reservations' => Reservation::withoutGlobalScope('tenant')->count(),
            'total_revenue' => (float) DB::table('subscriptions')
                ->whereNotNull('amount_paid')
                ->sum('amount_paid'),
        ];

        // Recent tenants
        $recentTenants = Tenant::query()
            ->with('subscriptionPlan:id,name')
            ->latest()
            ->take(5)
            ->get();

        // Monthly revenue chart data (last 6 months)
        $monthlyRevenue = Payment::withoutGlobalScope('tenant')
            ->where('status', 'completed')
            ->where('created_at', '>=', now()->subMonths(6))
            ->select(
                DB::raw('DATE_FORMAT(created_at, "%Y-%m") as month'),
                DB::raw('SUM(amount) as revenue')
            )
            ->groupBy('month')
            ->orderBy('month')
            ->get();

        $recentSubscriptions = DB::table('subscriptions as subscriptions')
            ->join('users as users', 'users.id', '=', 'subscriptions.user_id')
            ->leftJoin('tenants as tenants', 'tenants.id', '=', 'users.tenant_id')
            ->select([
                'subscriptions.id',
                'subscriptions.type',
                'subscriptions.stripe_status',
                'subscriptions.payment_method',
                'subscriptions.amount_paid',
                'subscriptions.currency',
                DB::raw('COALESCE(subscriptions.paid_at, subscriptions.updated_at, subscriptions.created_at) as paid_at'),
                'subscriptions.trial_ends_at',
                'users.name as user_name',
                'tenants.name as tenant_name',
            ])
            ->orderByRaw('COALESCE(subscriptions.paid_at, subscriptions.updated_at, subscriptions.created_at) DESC')
            ->limit(10)
            ->get();

        $trafficSources = DB::table('saas_visits')
            ->selectRaw("COALESCE(NULLIF(utm_source, ''), NULLIF(referrer_host, ''), 'Direct') as source")
            ->selectRaw('COUNT(*) as visits')
            ->where('visited_at', '>=', now()->subDays(30))
            ->groupBy('source')
            ->orderByDesc('visits')
            ->limit(8)
            ->get();

        $recentSaasVisits = SaasVisit::query()
            ->latest('visited_at')
            ->limit(10)
            ->get([
                'id',
                'landing_path',
                'referrer_host',
                'utm_source',
                'utm_medium',
                'utm_campaign',
                'visited_at',
            ])
            ->map(function (SaasVisit $visit) {
                return [
                    'id' => $visit->id,
                    'landing_path' => $visit->landing_path,
                    'source' => $visit->utm_source ?: ($visit->referrer_host ?: 'Direct'),
                    'medium' => $visit->utm_medium,
                    'campaign' => $visit->utm_campaign,
                    'visited_at' => $visit->visited_at?->toISOString(),
                ];
            })
            ->values();

        $recentLandingContacts = Ticket::withoutTenantScope()
            ->where('channel', 'landing')
            ->with([
                'messages' => fn ($q) => $q->latest()->limit(1),
            ])
            ->latest()
            ->limit(5)
            ->get()
            ->map(function (Ticket $ticket) {
                $lastMessage = $ticket->messages->first();

                return [
                    'id' => $ticket->id,
                    'ticket_number' => $ticket->ticket_number,
                    'guest_name' => $ticket->guest_name,
                    'guest_email' => $ticket->guest_email,
                    'subject' => $ticket->subject,
                    'status' => $ticket->status?->value ?? (string) $ticket->status,
                    'created_at' => $ticket->created_at?->toISOString(),
                    'last_message' => $lastMessage?->message,
                    'last_message_at' => $lastMessage?->created_at?->toISOString(),
                ];
            })
            ->values();

        return Inertia::render('SuperAdmin/Dashboard', [
            'stats' => $stats,
            'recentTenants' => $recentTenants,
            'monthlyRevenue' => $monthlyRevenue,
            'recentSubscriptions' => $recentSubscriptions,
            'tenants' => $tenants,
            'trafficSources' => $trafficSources,
            'recentSaasVisits' => $recentSaasVisits,
            'recentLandingContacts' => $recentLandingContacts,
            'urls' => [
                'landing_leads_index' => route('superadmin.landing-leads.index'),
            ],
        ]);
    }
}
