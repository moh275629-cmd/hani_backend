<?php

namespace App\Mail;

use App\Models\User;
use App\Models\RequiredDocuments;
use App\Models\Admin;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeClientMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $requiredDocuments;
    public $adminInfo;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user, $adminInfo = null)
    {
        $this->user = $user;
        $this->adminInfo = $adminInfo;
        
        // Get required documents for clients
        $this->requiredDocuments = RequiredDocuments::getRequiredForUserCategory('client');
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Welcome to Hani App - Account Verified!',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.welcome-client',
            with: [
                'user' => $this->user,
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
