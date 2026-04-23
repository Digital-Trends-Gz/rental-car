<?php
// app/Models/Ticket.php

namespace App\Models;

use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use App\Traits\BelongsToTenant;

class Ticket extends Model
{
    use HasFactory;
    use BelongsToTenant;

    protected $fillable = [
        'tenant_id',
        'ticket_number',
        'subject',
        'status',
        'channel',
        'user_id',
        'assigned_to_user_id',
        'guest_name',
        'guest_email',
        'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
        'status' => TicketStatus::class,
    ];

    protected static function boot()
    {
        parent::boot();

        static::creating(function ($ticket) {
            if (empty($ticket->ticket_number)) {
                $ticket->ticket_number = static::generateTicketNumber();
            }
        });
    }

    public static function generateTicketNumber()
    {
        $lastTicket = static::withoutTenantScope()
            ->orderBy('id', 'desc')
            ->first();

        $number = $lastTicket ? $lastTicket->id + 1 : 1;

        return 'TICK-' . str_pad($number, 6, '0', STR_PAD_LEFT);
    }

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id')
            ->withoutGlobalScope('tenant');
    }


    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }


    public function scopeForUser($query, $userId)
    {
        return $query->where('user_id', $userId);
    }

    public function scopeGuest($query)
    {
        return $query->where('channel', 'guest');
    }

    public function scopeCustomer($query)
    {
        return $query->where('channel', 'customer');
    }

    public function scopeTenant($query)
    {
        return $query->where('channel', 'tenant');
    }

    // Accessors & Mutators
    public function getIsGuestAttribute()
    {
        return is_null($this->user_id);
    }

    public function getCustomerNameAttribute()
    {
        return $this->user ? $this->user->name : $this->guest_name;
    }

    public function getCustomerEmailAttribute()
    {
        return $this->user ? $this->user->email : $this->guest_email;
    }

    public function getLastMessageAttribute()
    {
        return $this->messages()->latest()->first();
    }

}
