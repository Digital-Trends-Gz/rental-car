<?php

use App\Core\TenantContext;
use App\Enums\TicketStatus;
use App\Models\Tenant;
use App\Models\Ticket;

test('ticket numbers remain unique across tenants', function () {
    $tenantA = Tenant::factory()->create(['is_active' => true]);
    TenantContext::set($tenantA);

    $firstTicket = Ticket::create([
        'channel' => 'guest',
        'subject' => 'First support request',
        'status' => TicketStatus::NEW->value,
        'guest_name' => 'Tenant A Guest',
        'guest_email' => 'tenant-a@example.com',
    ]);

    expect($firstTicket->ticket_number)->toBe('TICK-000001');

    $tenantB = Tenant::factory()->create(['is_active' => true]);
    TenantContext::set($tenantB);

    $secondTicket = Ticket::create([
        'channel' => 'guest',
        'subject' => 'Second support request',
        'status' => TicketStatus::NEW->value,
        'guest_name' => 'Tenant B Guest',
        'guest_email' => 'tenant-b@example.com',
    ]);

    expect($secondTicket->ticket_number)->toBe('TICK-000002');
});
