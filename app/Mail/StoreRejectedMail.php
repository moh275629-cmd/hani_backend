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
use App\Models\RequiredDocuments;

class StoreRejectedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $store;
    public $requiredDocuments;
    public $reason; // ✅ add reason property

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Store $store, string $reason)
    {
        $this->user = $user;
        $this->store = $store;
        $this->reason = $reason; // ✅ assign it
        $this->requiredDocuments = RequiredDocuments::getRequiredForUserCategory('store');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Store Rejected - Hani App',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.store-rejected',
            with: [
                'user' => $this->user,
                'store' => $this->store,
                'reason' => $this->reason, // ✅ now works
                'requiredDocuments' => $this->requiredDocuments,
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
