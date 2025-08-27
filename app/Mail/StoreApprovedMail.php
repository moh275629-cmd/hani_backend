<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;
use App\Models\Store;

class StoreApprovedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $store;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Store $store)
    {
        $this->user = $user;
        $this->store = $store;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Store Approved - Hani App',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.store-approved',
            with: [
                'user' => $this->user,
                'store' => $this->store,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
