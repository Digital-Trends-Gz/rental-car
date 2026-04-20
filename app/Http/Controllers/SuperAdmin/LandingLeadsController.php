<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\TicketStatus;
use App\Http\Controllers\Controller;
use App\Mail\LandingLeadReplyMail;
use App\Models\Ticket;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Support\Facades\Mail;
use Inertia\Inertia;
use Inertia\Response;

class LandingLeadsController extends Controller
{
    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());

        $tickets = Ticket::withoutTenantScope()
            ->where('channel', 'landing')
            ->with(['messages' => fn ($q) => $q->latest()->limit(1)])
            ->when($status && $status !== 'all', fn ($q) => $q->where('status', $status))
            ->when($search !== '', function ($q) use ($search) {
                $q->where(function ($w) use ($search) {
                    $w->where('ticket_number', 'like', "%{$search}%")
                        ->orWhere('subject', 'like', "%{$search}%")
                        ->orWhere('guest_name', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%")
                        ->orWhereHas('messages', fn ($mq) => $mq->where('message', 'like', "%{$search}%"));
                });
            })
            ->latest()
            ->paginate(15)
            ->through(function (Ticket $ticket) {
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
            ->withQueryString();

        return Inertia::render('SuperAdmin/LandingLeads/Index', [
            'tickets' => $tickets,
            'filters' => [
                'search' => $search,
                'status' => $status ?: 'all',
            ],
            'statuses' => collect(TicketStatus::cases())->map(fn (TicketStatus $statusCase) => [
                'value' => $statusCase->value,
                'label' => $statusCase->label(),
                'color' => $statusCase->color(),
            ])->all(),
            'urls' => [
                'index' => route('superadmin.landing-leads.index'),
            ],
        ]);
    }

    public function show(Ticket $ticket): Response
    {
        $this->abortIfNotLandingLead($ticket);

        $ticket->load([
            'messages' => fn ($q) => $q->orderBy('created_at'),
            'messages.user:id,name,email,role',
        ]);

        return Inertia::render('SuperAdmin/LandingLeads/Show', [
            'ticket' => [
                'id' => $ticket->id,
                'ticket_number' => $ticket->ticket_number,
                'subject' => $ticket->subject,
                'status' => $ticket->status?->value ?? (string) $ticket->status,
                'created_at' => $ticket->created_at?->toISOString(),
                'guest_name' => $ticket->guest_name,
                'guest_email' => $ticket->guest_email,
                'messages' => $ticket->messages->map(fn ($message) => [
                    'id' => $message->id,
                    'message' => $message->message,
                    'user_id' => $message->user_id,
                    'user_name' => $message->user?->name ?? 'System',
                    'is_superadmin' => $message->user?->role?->value === 'super_admin' || (bool) $message->is_admin,
                    'created_at' => $message->created_at,
                ])->values(),
            ],
            'statuses' => collect(TicketStatus::cases())->map(fn (TicketStatus $statusCase) => [
                'value' => $statusCase->value,
                'label' => $statusCase->label(),
            ])->values(),
            'urls' => [
                'index' => route('superadmin.landing-leads.index'),
                'reply' => route('superadmin.landing-leads.reply', ['ticket' => $ticket->id]),
                'status' => route('superadmin.landing-leads.status', ['ticket' => $ticket->id]),
            ],
        ]);
    }

    public function reply(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->abortIfNotLandingLead($ticket);

        $validated = $request->validate([
            'message' => ['required', 'string', 'min:2'],
        ]);

        $ticket->messages()->create([
            'tenant_id' => null,
            'user_id' => $request->user()->id,
            'message' => $validated['message'],
            'is_admin' => true,
        ]);

        if ($ticket->guest_email) {
            Mail::to(new Address(
                $ticket->guest_email,
                $ticket->guest_name ?: $ticket->guest_email,
            ))->send(new LandingLeadReplyMail($ticket, $validated['message']));
        }

        if (($ticket->status?->value ?? (string) $ticket->status) === TicketStatus::NEW->value) {
            $ticket->update(['status' => TicketStatus::IN_PROGRESS]);
        }

        return back()->with('success', 'Reply sent and emailed to the guest.');
    }

    public function updateStatus(Request $request, Ticket $ticket): RedirectResponse
    {
        $this->abortIfNotLandingLead($ticket);

        $validated = $request->validate([
            'status' => ['required', Rule::in(array_map(fn (TicketStatus $status) => $status->value, TicketStatus::cases()))],
        ]);

        $ticket->update([
            'status' => $validated['status'],
        ]);

        return back()->with('success', 'Lead status updated.');
    }

    private function abortIfNotLandingLead(Ticket $ticket): void
    {
        abort_unless($ticket->channel === 'landing', 404);
    }
}
