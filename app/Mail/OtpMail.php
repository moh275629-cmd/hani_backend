<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otp;
    public $email;
    public $userRole;

    /**
     * Create a new message instance.
     */
    public function __construct($otp, $email, $userRole = null)
    {
        $this->otp = $otp;
        $this->email = $email;
        $this->userRole = $userRole;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = 'Your OTP Code - Hani App';
        if ($this->userRole === 'store') {
            $subject .= ' - Store Registration';
        }
        
        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $view = 'emails.otp';
        $data = [
            'otp' => $this->otp,
            'email' => $this->email,
        ];
        
        // Use store-specific template for store role
        if ($this->userRole === 'store') {
            $view = 'emails.otp-store';
        }
        
        return new Content(
            view: $view,
            with: $data,
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
