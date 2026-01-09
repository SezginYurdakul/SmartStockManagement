<?php

namespace App\Mail;

use App\Models\UserInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     */
    public function __construct(
        public UserInvitation $invitation
    ) {}

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $companyName = $this->invitation->company->name;
        
        return new Envelope(
            subject: "You're invited to join {$companyName}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        $acceptUrl = config('app.frontend_url') . '/accept-invitation?token=' . $this->invitation->token;
        $expiresAt = $this->invitation->expires_at->format('F j, Y \a\t g:i A');
        $inviterName = $this->invitation->inviter->full_name;
        $companyName = $this->invitation->company->name;

        return new Content(
            view: 'emails.user-invitation',
            with: [
                'acceptUrl' => $acceptUrl,
                'expiresAt' => $expiresAt,
                'inviterName' => $inviterName,
                'companyName' => $companyName,
                'email' => $this->invitation->email,
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
