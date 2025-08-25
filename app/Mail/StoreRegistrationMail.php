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

class StoreRegistrationMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $store;
    public $requiredDocuments;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Store $store)
    {
        $this->user = $user;
        $this->store = $store;
        $this->requiredDocuments = RequiredDocuments::getRequiredForUserCategory('store');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Store Registration - Required Documents - Hani App',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.store-registration',
            with: [
                'user' => $this->user,
                'store' => $this->store,
                'requiredDocuments' => $this->requiredDocuments,
            ],
        );
    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
