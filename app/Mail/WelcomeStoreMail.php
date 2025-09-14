<?php

namespace App\Mail;

use App\Models\User;
use App\Models\Store;
use App\Models\RequiredDocuments;
use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeStoreMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $store;
    public $requiredDocuments;
    public $adminInfo;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, Store $store, $adminInfo = null)
    {
        $this->user = $user;
        $this->store = $store;
        $this->adminInfo = $adminInfo;
        
        // Get required documents for stores
        $this->requiredDocuments = RequiredDocuments::getRequiredForUserCategory('store');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Hani App - Store Registration Verified!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-store',
            with: [
                'user' => $this->user,
                'store' => $this->store,
                'requiredDocuments' => $this->requiredDocuments,
                'adminInfo' => $this->adminInfo,
            ]
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
