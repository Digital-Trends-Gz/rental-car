<?php

namespace App\Mail;

use App\Models\Ticket;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class LandingLeadReplyMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Ticket $ticket,
        public string $replyMessage,
    ) {
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: sprintf('Re: %s', $this->ticket->subject),
            from: config('mail.from.address')
                ? new \Illuminate\Mail\Mailables\Address(
                    config('mail.from.address'),
                    config('mail.from.name', config('app.name'))
                )
                : null,
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.landing-lead-reply',
            with: [
                'ticket' => $this->ticket,
                'replyMessage' => $this->replyMessage,
            ],
        );
    }
}
